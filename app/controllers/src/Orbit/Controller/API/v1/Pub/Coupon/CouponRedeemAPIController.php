<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use Mall;
use Coupon;
use PromotionRetailer;
use Tenant;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use URL;
use Validator;
use Activity;
use Language;
use Lang;
use CouponRetailer;
use Carbon\Carbon;
use IssuedCoupon;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Helper\Security\Encrypter;
use \Queue;
use \App;
use \Exception;
use \UserVerificationNumber;

class CouponRedeemAPIController extends ControllerAPI
{
    /**
     * POST - Pub Redeem Coupon
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string      `cid`                             (required) - Hashed issued coupon ID
     * @param string      `uid`                             (optional) - Hashed user identifier
     * @param string      `merchant_id`                     (required) - ID of the mall
     * @param string      `merchant_verification_number`    (required) - Merchant/Tenant verification number
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postPubRedeemCoupon()
    {
        $activity = Activity::mobileci()
                          ->setActivityType('coupon');

        $user = NULL;
        $mall = NULL;
        $mall_id = NULL;
        $issuedcoupon = NULL;
        $coupon = NULL;
        $httpCode = 200;

        try {
            $this->checkAuth();
            $user = $this->api->user;

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();

            $mallId = OrbitInput::post('mall_id');
            $storeId = OrbitInput::post('store_id');
            $couponId = OrbitInput::post('cid'); // hashed issued coupon id
            $userIdentifier = OrbitInput::post('uid', NULL); // hashed user identifier
            $verificationNumber = OrbitInput::post('merchant_verification_number');

            $encryptionKey = Config::get('orbit.security.encryption_key');
            $encryptionDriver = Config::get('orbit.security.encryption_driver');
            $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

            $requestedCouponId = $encrypter->decrypt($couponId);

            // requested coupon before validation
            $coupon = Coupon::excludeDeleted('promotions')
                ->where('promotion_id', $requestedCouponId)
                ->first();

            $validator = Validator::make(
                array(
                    'store_id'                      => $storeId,
                    'mall_id'                       => $mallId,
                    'cid'                           => $couponId,
                    'merchant_verification_number'  => $verificationNumber,
                ),
                array(
                    'store_id'                      => 'required',
                    'mall_id'                       => 'required|orbit.empty.merchant',
                    'cid'                           => 'required',
                    'merchant_verification_number'  => 'required'
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (! empty($userIdentifier)) {
                $encryptionKey = Config::get('orbit.security.encryption_key');
                $encryptionDriver = Config::get('orbit.security.encryption_driver');
                $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

                $userIdentifier = $encrypter->decrypt($userIdentifier);
            }

            $isAvailable = (new IssuedCoupon())->issueCouponViaEmail($requestedCouponId, $userIdentifier);
            if (! is_object($isAvailable)) {
                $errorMessage = 'Issued coupon is not found';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $tenant = Tenant::join('promotion_retailer_redeem', 'promotion_retailer_redeem.retailer_id', '=', 'merchants.merchant_id')
                ->where('promotion_id', $requestedCouponId)
                ->where('merchant_id', $storeId)
                ->where('masterbox_number', $verificationNumber)
                ->first();

            $csVerificationNumber = UserVerificationNumber::
                join('promotion_employee', 'promotion_employee.user_id', '=', 'user_verification_numbers.user_id')
                ->where('promotion_employee.promotion_id', $requestedCouponId)
                ->where('merchant_id', $mallId)
                ->where('verification_number', $verificationNumber)
                ->first();

            $redeem_retailer_id = NULL;
            $redeem_user_id = NULL;
            if (! is_object($tenant) && ! is_object($csVerificationNumber)) {
                // @Todo replace with language
                $errorMessage = Lang::get('mobileci.coupon.wrong_verification_number');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } else {
                if (is_object($tenant)) {
                    $redeem_retailer_id = $tenant->merchant_id;
                }
                if (is_object($csVerificationNumber)) {
                    $redeem_user_id = $csVerificationNumber->user_id;
                }
            }

            $mall = App::make('orbit.empty.merchant');

            $isAvailable->redeemed_date = date('Y-m-d H:i:s');
            $isAvailable->redeem_retailer_id = $redeem_retailer_id;
            $isAvailable->redeem_user_id = $redeem_user_id;
            $isAvailable->redeem_verification_code = $verificationNumber;
            $isAvailable->status = 'redeemed';

            $isAvailable->save();

            // Commit the changes
            $this->commit();

            $this->response->message = 'Coupon has been successfully redeemed.';
            $this->response->data = $isAvailable->issued_coupon_code;

            // customize user property before saving activity
            $user = $couponHelper->customizeUserProps($user, $userIdentifier);

            $activityNotes = sprintf('Coupon Redeemed: %s. Issued Coupon Id: %s.', $coupon->promotion_name, $isAvailable->issued_coupon_id);
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Successful')
                    ->setObject($coupon)
                    ->setCoupon($coupon)
                    ->setNotes($activityNotes)
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseOK();

        } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($coupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($coupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($coupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        } catch (QueryException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($coupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('redeem_coupon')
                    ->setActivityNameLong('Coupon Redemption Failed')
                    ->setObject($coupon)
                    ->setCoupon($coupon)
                    ->setNotes($e->getMessage())
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->responseFailed();
        }

        $output = $this->render($httpCode);

        // Save the activity
        $activity->save();

        return $output;
    }
}
