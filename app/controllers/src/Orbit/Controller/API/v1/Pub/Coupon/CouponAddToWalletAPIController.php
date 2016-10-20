<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

use OrbitShop\API\v1\ControllerAPI;
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
use Orbit\Helper\Session\UserGetter;
use Lang;
use \Exception;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Activity;
use Coupon;
use IssuedCoupon;
use Orbit\Helper\Security\Encrypter;

class CouponAddToWalletAPIController extends ControllerAPI
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
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You must login to access this.';
                ACL::throwAccessForbidden($message);
            }

            $hashedIssuedCouponCode = OrbitInput::post('cid', NULL); // hashed issued coupon code
            $hashedPromotionCouponId = OrbitInput::post('pid', NULL); // hashed promotion id

            // add to wallet via SMS
            if (! empty($hashedIssuedCouponCode) && ! empty($hashedPromotionCouponId)) {
                $data = $this->decryptCouponParams($hashedIssuedCouponCode, $hashedPromotionCouponId);
                if ($data->message === 'OK') {
                    $coupon_id = $data->promotionId;
                    $issued_coupon_code = $data->issuedCouponCode;
                }
            }

            $couponHelper = CouponHelper::create($this->session);
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

            $coupon = Coupon::excludeDeleted()
                ->where('promotion_id', '=', $coupon_id)
                ->first();

            $newIssuedCoupon = new IssuedCoupon();
            $issuedCoupon = $newIssuedCoupon->issueCoupon($coupon->promotion_id, $user->user_email, $user->user_id, $issued_coupon_code);
            $this->commit();

            if ($issuedCoupon) {
                $this->response->message = 'Request Ok';
                $this->response->data = NULL;
                $activityNotes = sprintf('Added to wallet Coupon Id: %s. Issued Coupon Id: %s', $coupon->promotion_id, $issuedCoupon->issued_coupon_id);
                $activity->setUser($user)
                    ->setActivityName('click_add_to_wallet')
                    ->setActivityNameLong('Click Landing Page Add To Wallet')
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
            }

        } catch (ACLForbiddenException $e) {
            $coupon = Coupon::where('promotion_id', '=', $coupon_id)->first();

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
            $activityNotes = sprintf('Failed to add to wallet. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('click_add_to_wallet')
                ->setActivityNameLong('Click Landing Page Add To Wallet')
                ->setObject($coupon)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setLocation($retailer)
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
            $activityNotes = sprintf('Failed to add to wallet. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('click_add_to_wallet')
                ->setActivityNameLong('Click Landing Page Add To Wallet Failed')
                ->setObject($issuedCoupon)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setLocation($retailer)
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = $e->getLine();
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getFile();
            $this->rollback();
            $activityNotes = sprintf('Failed to add to wallet. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('click_add_to_wallet')
                ->setActivityNameLong('Click Landing Page Add To Wallet Failed')
                ->setObject($issuedCoupon)
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
