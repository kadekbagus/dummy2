<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Orbit\Helper\Net\SessionPreparer;
use Lang;
use \Exception;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Activity;
use Coupon;
use Mall;
use IssuedCoupon;
use Orbit\Helper\Security\Encrypter;
use \Orbit\Helper\Exception\OrbitCustomException;
use Event;

class CouponAddToWalletAPIController extends PubControllerAPI
{
    /**
     * POST - add to wallet
     *
     * @param string coupon_id
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author ahmad <ahmad@dominopos.com>
     */
    public function postAddToWallet()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('click');
        $user = NULL;
        $coupon = NULL;
        $issuedCoupon = NULL;
        $retailer = null;
        $issued_coupon_code = null;
        $coupon_id = OrbitInput::post('coupon_id', NULL);
        try {
            $user = $this->getUser();

            $session = SessionPreparer::prepareSession();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You must login to access this.';
                ACL::throwAccessForbidden($message);
            }

            $hashedIssuedCouponCode = OrbitInput::post('cid', NULL); // hashed issued coupon code
            $hashedPromotionCouponId = OrbitInput::post('pid', NULL); // hashed promotion id
            $mallId = OrbitInput::post('mall_id', NULL);

            // add to wallet via SMS
            if (! empty($hashedIssuedCouponCode) && ! empty($hashedPromotionCouponId)) {
                $data = $this->decryptCouponParams($hashedIssuedCouponCode, $hashedPromotionCouponId);
                if ($data->message === 'OK') {
                    $coupon_id = $data->promotionId;
                    $issued_coupon_code = $data->issuedCouponCode;
                }
            }

            // added this to make coupon recorded even the validator fails
            $coupon = Coupon::excludeDeleted()
                ->where('promotion_id', '=', $coupon_id)
                ->first();

            $couponHelper = CouponHelper::create($session);
            $couponHelper->couponCustomValidator();

            $validator = Validator::make(
                array(
                    'coupon_id' => $coupon_id,
                ),
                array(
                    'coupon_id' => 'required|orbit.exists.coupon|orbit.notexists.couponwallet',
                ),
                array(
                    'orbit.exists.coupon' => Lang::get('validation.orbit.empty.coupon'),
                    'orbit.notexists.couponwallet' => 'Coupon already added to wallet'
                )
            );

            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (! empty($issued_coupon_code) && ! empty($coupon_id)) {
                $validator2 = Validator::make(
                    array(
                        'issued_coupon_code' => $issued_coupon_code,
                    ),
                    array(
                        'issued_coupon_code' => 'orbit.exists.issued_coupon_code_sms:' . $coupon_id,
                    ),
                    array(
                        'orbit.exists.issued_coupon_code_sms' => 'Invalid issued coupon code'
                    )
                );

                // Run the validation
                if ($validator2->fails()) {
                    $errorMessage = $validator2->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }

            if ($coupon->is_unique_redeem === 'Y') {
                $checkIssued = IssuedCoupon::where('promotion_id', $coupon->promotion_id)
                                           ->where('user_id', $user->user_id)
                                           ->where('status', '!=', 'deleted')
                                           ->first();

                if (is_object($checkIssued)) {
                    OrbitShopAPI::throwInvalidArgument('Sorry, you can only get one coupon at a time.');
                }
            }

            $isAvailable = Coupon::leftJoin('issued_coupons', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                ->where('issued_coupons.status', 'available')
                ->where('promotions.status', 'active')
                ->where('promotions.promotion_id', '=', $coupon_id)
                ->first();

            if (! is_object($isAvailable)) {
                $errorMessage = 'There is no available coupon.';
                throw new OrbitCustomException($errorMessage, Coupon::NO_AVAILABLE_COUPON_ERROR_CODE, NULL);
            }

            $newIssuedCoupon = new IssuedCoupon();
            $issuedCoupon = $newIssuedCoupon->issueCouponViaWallet($coupon->promotion_id, $user->user_email, $user->user_id, $issued_coupon_code);
            $this->commit();

            Event::fire('orbit.coupon.postaddtowallet.after.commit', array($this, $coupon_id));

            if (! empty($mallId)) {
                $retailer = Mall::excludeDeleted()
                    ->where('merchant_id', $mallId)
                    ->first();
            }

            if ($issuedCoupon) {
                $this->response->message = 'Request Ok';
                $this->response->data = NULL;
                $activityNotes = sprintf('Successfully added to wallet Coupon Id: %s. Issued Coupon Id: %s', $coupon->promotion_id, $issuedCoupon->issued_coupon_id);
                $activity->setUser($user)
                    ->setActivityName('coupon_added_to_wallet')
                    ->setActivityNameLong('Coupon Added To Wallet')
                    ->setLocation($retailer)
                    ->setObject($issuedCoupon)
                    ->setModuleName('Coupon')
                    ->setCoupon($coupon)
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
                $this->response->message = 'Fail to issue coupon';
                $this->response->data = NULL;

                $activityNotes = sprintf('Failed to add to wallet. Coupon Id: %s. Error: %s', $coupon_id, $this->response->message);
                $activity->setUser($user)
                    ->setActivityName('coupon_added_to_wallet')
                    ->setActivityNameLong('Coupon Added To Wallet Failed')
                    ->setLocation($retailer)
                    ->setObject($issuedCoupon)
                    ->setModuleName('Coupon')
                    ->setCoupon($coupon)
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
        } catch (InvalidArgsException $e) {
            $coupon = Coupon::where('promotion_id', '=', $coupon_id)->first();

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
            $activityNotes = sprintf('Failed to add to wallet. Coupon Id: %s. Error: %s', $coupon_id, $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('coupon_added_to_wallet')
                ->setActivityNameLong('Coupon Added To Wallet Failed')
                ->setObject($coupon)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setLocation($retailer)
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            $coupon = Coupon::where('promotion_id', '=', $coupon_id)->first();

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
            $activityNotes = sprintf('Failed to add to wallet. Coupon Id: %s. Error: %s', $coupon_id, $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('coupon_added_to_wallet')
                ->setActivityNameLong('Coupon Added To Wallet Failed')
                ->setObject($coupon)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setLocation($retailer)
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();

        } catch (Exception $e) {
            $coupon = Coupon::where('promotion_id', '=', $coupon_id)->first();

            $this->response->code = $e->getCode();
            $this->response->status = $e->getLine();
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getFile();
            $this->rollback();
            $activityNotes = sprintf('Failed to add to wallet. Coupon Id: %s. Error: %s', $coupon_id, $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('coupon_added_to_wallet')
                ->setActivityNameLong('Coupon Added To Wallet Failed')
                ->setObject($coupon)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setLocation($retailer)
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        }

        return $this->render();
    }

    private function decryptCouponParams($cid, $pid)
    {
        try {
            $encryptionKey = Config::get('orbit.security.encryption_key');
            $encryptionDriver = Config::get('orbit.security.encryption_driver');
            $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

            $message = 'OK';
            $issuedCouponCode = $encrypter->decrypt($cid);
            $promotionId = $encrypter->decrypt($pid);

        } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e) {
            $message = $e->getMessage();
        } catch (Exception $e) {
            $message = $e->getMessage();
        }

        $data = new \stdClass();
        $data->message = $message;
        $data->issuedCouponCode = $issuedCouponCode;
        $data->promotionId = $promotionId;

        return $data;
    }
}
