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

            $coupon = Coupon::excludeDeleted()
                ->where('promotion_id', '=', $coupon_id)
                ->first();

            $newIssuedCoupon = new IssuedCoupon();
            $issuedCoupon = $newIssuedCoupon->issue($coupon, $user->user_id, $user);
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
}
