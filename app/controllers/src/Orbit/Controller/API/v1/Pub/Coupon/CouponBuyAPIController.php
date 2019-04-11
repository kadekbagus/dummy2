<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \Orbit\Helper\Exception\OrbitCustomException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use DB;
use Validator;
use Activity;
use Mall;
use Lang;
use \Exception;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Coupon;
use IssuedCoupon;
use \Queue;
use Carbon\Carbon as Carbon;
use Log;

class CouponBuyAPIController extends PubControllerAPI
{
    /**
     * POST - For check and reserved the coupon per user
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string coupon_id
     * @param string with_reserved
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postCouponBuy()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $response = NULL;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $coupon_id = OrbitInput::post('coupon_id');
            $with_reserved = OrbitInput::post('with_reserved', 'N');
            $quantity = OrbitInput::post('quantity');
            $limitTimeCfg = Config::get('orbit.coupon_reserved_limit_time', 10);

            $this->registerCustomValidation();
            CouponHelper::create()->couponCustomValidator();

            $validator = Validator::make(
                array(
                    'coupon_id' => $coupon_id,
                    'with_reserved' => $with_reserved,
                    'quantity' => $quantity,
                ),
                array(
                    'coupon_id' => 'required|orbit.exists.coupon',
                    'with_reserved' => 'required',
                    'quantity' => 'required|orbit.allowed.quantity',
                ),
                array(
                    'orbit.allowed.quantity' => 'REQUESTED_QUANTITY_NOT_AVAILABLE',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $coupon = Coupon::findOrFail($coupon_id);

            // Issued coupon count for current Customer.
            $userCouponCount = IssuedCoupon::where('user_id', $user->user_id)
                                            ->where('promotion_id', $coupon_id)
                                            ->whereIn('status', [
                                                IssuedCoupon::STATUS_ISSUED,
                                                IssuedCoupon::STATUS_REDEEMED,
                                                IssuedCoupon::STATUS_RESERVED
                                            ])->count();

            $maxQuantityPerUser = 9999;
            $availableCouponForUser = 9999;
            if (! empty($coupon->max_quantity_per_user)) {
                $maxQuantityPerUser = $coupon->max_quantity_per_user;
                $availableCouponForUser = $maxQuantityPerUser - $userCouponCount;
            }

            if ($quantity > $availableCouponForUser) {
                $errorMessage = 'MAXIMUM_PURCHASE_REACHED|' . $availableCouponForUser;
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // If we found reserved coupon for this user and it is a unique coupon, then
            // we should mark it as not available to be purchased again.
            if ($coupon->is_unique_redeem === 'Y') {
                $usedCoupon = IssuedCoupon::where('user_id', $user->user_id)
                                            ->where('promotion_id', $coupon_id)
                                            ->whereIn('status', [IssuedCoupon::STATUS_RESERVED, IssuedCoupon::STATUS_ISSUED, IssuedCoupon::STATUS_REDEEMED])
                                            ->first();

                if (! empty($usedCoupon)) {
                    $errorMessage = 'Requested quantity not available. You only able to purchase 1 unique coupon.';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }

            // Check the user already have coupon or not
            $userIssuedCoupon = IssuedCoupon::where('user_id', $user->user_id)
                                            ->where('promotion_id', $coupon_id)
                                            ->where(function($query) {
                                                $query->whereNull('transaction_id')->orWhere('transaction_id', '');
                                            })
                                            ->where('status', IssuedCoupon::STATUS_RESERVED)
                                            ->first();

            if (! empty($userIssuedCoupon)) {
                $userIssuedCoupon->limit_time = date('Y-m-d H:i:s', strtotime("+$limitTimeCfg minutes", strtotime($userIssuedCoupon->issued_date)));
            }

            if ($with_reserved === 'N') {

                $response = $userIssuedCoupon;

            } elseif ($with_reserved === 'Y') {

                $this->beginTransaction();

                $arrIssuedCoupons = [];
                for($i = 1; $i <= $quantity; $i++) {
                    //insert for sepulsa and update for hot_deals
                    if ($coupon->promotion_type === Coupon::TYPE_SEPULSA) {
                        $issuedCoupon = new IssuedCoupon;
                        $issuedCoupon->promotion_id  = $coupon_id;
                        $issuedCoupon->user_id       = $user->user_id;
                        $issuedCoupon->user_email    = $user->user_email;
                        $issuedCoupon->issued_date   = date('Y-m-d H:i:s');
                        $issuedCoupon->status        = IssuedCoupon::STATUS_RESERVED;
                        $issuedCoupon->record_exists = 'Y';
                    } elseif ($coupon->promotion_type === Coupon::TYPE_HOT_DEALS || $coupon->promotion_type === Coupon::TYPE_GIFTNCOUPON) {
                        $issuedCoupon = IssuedCoupon::where('promotion_id', $coupon_id)
                                                        ->available()
                                                        ->first();

                        $issuedCoupon->user_id     = $user->user_id;
                        $issuedCoupon->user_email  = $user->user_email;
                        $issuedCoupon->issued_date = date('Y-m-d H:i:s');
                        $issuedCoupon->status      = IssuedCoupon::STATUS_RESERVED;
                    }

                    $issuedCoupon->save();
                    $arrIssuedCoupons[] = $issuedCoupon->issued_coupon_id;
                }

                // Update coupon availability if the quantity changed.
                // if ($remainingQuantity > 0) {
                //     $coupon->updateAvailability();
                // }

                $this->commit();

                $date = Carbon::now()->addMinutes($limitTimeCfg);
                Log::info('Send CheckReservedCoupon queue, issued_coupon_id =  '. $issuedCoupon->issued_coupon_id .', will running at = ' . $date);

                Queue::later(
                    $date,
                    'Orbit\\Queue\\Coupon\\CheckReservedCoupon',
                    ['coupon_id' => $coupon_id, 'user_id' => $user->user_id, 'issued_coupons' => $arrIssuedCoupons]
                );

                $issuedCoupon->limit_time = date('Y-m-d H:i:s', strtotime("+$limitTimeCfg minutes", strtotime($issuedCoupon->issued_date)));

                // Return the data
                $response = $issuedCoupon;
            }

            $this->response->data = $response;

        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            $this->rollBack();
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            $this->rollBack();
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
            $this->rollBack();
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
            $this->rollBack();
        }

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * Register custom request validations.
     *
     * @return [type] [description]
     */
    private function registerCustomValidation()
    {
        $user = $this->getUser();

        /**
         * Normally, available coupon = max_issued_coupon - sum(reserved, issued, redeemed).
         * But when creating payment from PaymentMidtransCreate we should not count the quantity that we already reserved.
         */
        Validator::extend('orbit.allowed.quantity', function ($attribute, $requestedQuantity, $parameters) use ($user) {

            $couponId = OrbitInput::post('coupon_id');
            if (empty($couponId)) {
                $couponId = OrbitInput::post('object_id');
            }

            $coupon = Coupon::select('maximum_issued_coupon', 'max_quantity_per_purchase', 'is_unique_redeem')
                              ->findOrFail($couponId);

            // Globally issued coupon count regardless of the Customer.
            $issuedCouponCount = IssuedCoupon::where('promotion_id', $couponId)
                                    ->whereIn('status', [
                                        IssuedCoupon::STATUS_ISSUED,
                                        IssuedCoupon::STATUS_REDEEMED,
                                        IssuedCoupon::STATUS_RESERVED
                                    ])->count();

            // If max_quantity in DB is empty, then assume it is old data.
            // We should fallback to value defined in config file.
            $maxQuantityPerPurchase = empty($coupon->max_quantity_per_purchase) ?
                                    Config::get('orbit.transaction.max_quantity_per_purchase', 1) :
                                    $coupon->max_quantity_per_purchase;

            // Available coupon globally.
            $availableCoupon = $coupon->maximum_issued_coupon - $issuedCouponCount;

            // Customer should be able to buy if requested quantity is:
            // - lower than available coupon (globally),
            // - lower than maximum quantity per purchase
            return $requestedQuantity <= $availableCoupon && $requestedQuantity <= $maxQuantityPerPurchase;
        });
    }
}
