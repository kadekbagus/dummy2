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
use \Queue;
use Mall;

class CouponAddToEmailAPIController extends ControllerAPI
{
    /**
     * POST - add coupon to email
     *
     * @param string coupon_id
     * @param string email
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author ahmad <ahmad@dominopos.com>
     */
    public function postAddCouponToEmail()
    {
        $activity = Activity::mobileci()
                            ->setActivityType('click');
        $user = NULL;
        $coupon = NULL;
        $issuedCoupon = NULL;
        $retailer = null;
        $email = NULL;
        try {
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();

            $coupon_id = OrbitInput::post('coupon_id');
            $email = OrbitInput::post('email');
            $mallId = OrbitInput::post('mall_id');

            $validator = Validator::make(
                array(
                    'coupon_id' => $coupon_id,
                    'email' => $email,
                ),
                array(
                    'coupon_id' => 'required|orbit.exists.coupon',
                    'email' => 'required|email',
                ),
                array(
                    'orbit.exists.coupon' => Lang::get('validation.orbit.empty.coupon'),
                )
            );

            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $coupon = Coupon::excludeDeleted()
                ->where('promotion_id', '=', $coupon_id)
                ->first();

            $newIssuedCoupon = new IssuedCoupon();
            $issuedCoupon = $newIssuedCoupon->issue($coupon, $user->user_id);
            $this->commit();

            $encryptionKey = Config::get('orbit.security.encryption_key');
            $encryptionDriver = Config::get('orbit.security.encryption_driver');
            $encrypter = new Encrypter($encryptionKey, $encryptionDriver);

            $hashedIssuedCouponCid = rawurlencode($encrypter->encrypt($issuedCoupon->issued_coupon_id));
            $hashedIssuedCouponUid = rawurlencode($encrypter->encrypt($email));

            // cid=%s&uid=%s
            $redeem_url = sprintf(Config::get('orbit.coupon.direct_redemption_url'), $hashedIssuedCouponCid, $hashedIssuedCouponUid);

            // queue to send coupon redemption page url
            Queue::push('Orbit\\Queue\\IssuedCouponMailQueue', [
                'email' => $email,
                'issued_coupon_id' => $issuedCoupon->issued_coupon_id,
                'redeem_url' => $redeem_url
            ]);

            // customize user property before saving activity
            $user = $couponHelper->customizeUserProps($user, $email);

            if (! empty($mallId)) {
                $retailer = Mall::excludeDeleted()
                    ->where('merchant_id', $mallId)
                    ->first();
            }

            if ($issuedCoupon) {
                $this->response->message = 'Request Ok';
                $this->response->data = NULL;
                $activityNotes = sprintf('Issued to email: %s. Coupon Id: %s. Issued Coupon Id: %s', $email, $coupon->promotion_id, $issuedCoupon->issued_coupon_id);
                $activity->setUser($user)
                    ->setActivityName('issue_coupon')
                    ->setActivityNameLong('Issue Coupon by Email')
                    ->setObject($issuedCoupon)
                    ->setLocation($retailer)
                    ->setModuleName('Coupon')
                    ->setCoupon($coupon)
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
                $this->response->message = 'Fail to issue coupon';
                $this->response->data = NULL;
                $activityNotes = sprintf('Failed to issue to email: %s. Coupon Id: %s.', $email, $coupon->promotion_id);
                $activity->setUser($user)
                    ->setActivityName('issue_coupon')
                    ->setActivityNameLong('Failed to Issue Coupon by Email')
                    ->setObject($issuedCoupon)
                    ->setLocation($retailer)
                    ->setModuleName('Coupon')
                    ->setCoupon($coupon)
                    ->setNotes($activityNotes)
                    ->responseFailed()
                    ->save();
            }

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
            $activityNotes = sprintf('Failed to add to email. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('issue_coupon')
                ->setActivityNameLong('Failed to Issue Coupon by Email')
                ->setObject($issuedCoupon)
                ->setLocation($retailer)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
            $activityNotes = sprintf('Failed to add to email. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('issue_coupon')
                ->setActivityNameLong('Failed to Issue Coupon by Email')
                ->setObject($issuedCoupon)
                ->setLocation($retailer)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = $e->getLine();
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getFile();
            $this->rollback();
            $activityNotes = sprintf('Failed to add to email. Error: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('issue_coupon')
                ->setActivityNameLong('Failed to Issue Coupon by Email')
                ->setObject($issuedCoupon)
                ->setLocation($retailer)
                ->setModuleName('Coupon')
                ->setCoupon($coupon)
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        }

        return $this->render();
    }
}
