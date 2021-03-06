<?php namespace Orbit\Controller\API\v1\Pub\Payment;

/**
 * @author kadek <kadek@dominopos.com>
 * @desc Controller for create payment with Stripe
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use Validator;
use Coupon;
use PaymentTransaction;
use PaymentTransactionDetail;
use PaymentTransactionDetailNormalPaypro;
use PaymentMidtrans;
use IssuedCoupon;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Mall;
use Activity;
use Carbon\Carbon as Carbon;

class PaymentStripeCreateAPIController extends PubControllerAPI
{

    public function postPaymentStripeCreate()
    {
        $httpCode = 200;
        try {
            $this->checkAuth();
            $user = $this->api->user;

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            CouponHelper::create()->couponCustomValidator();
            $this->registerCustomValidation();

            $user_id = $user->user_id;
            $first_name = OrbitInput::post('first_name');
            $last_name = OrbitInput::post('last_name');
            $email = OrbitInput::post('email');
            $phone = OrbitInput::post('phone');
            $country_id = OrbitInput::post('country_id');
            $quantity = OrbitInput::post('quantity');
            $amount = OrbitInput::post('amount');
            $mall_id = OrbitInput::post('mall_id', 'gtm');
            $currency_id = OrbitInput::post('currency_id', '1');
            $currency = strtoupper(OrbitInput::post('currency', 'IDR'));
            $post_data = OrbitInput::post('post_data');
            $object_id = OrbitInput::post('object_id');
            $object_type = OrbitInput::post('object_type');
            $object_name = OrbitInput::post('object_name');
            $user_name = (!empty($last_name) ? $first_name.' '.$last_name : $first_name);
            $mallId = OrbitInput::post('mall_id', null);

            $validator = Validator::make(
                array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $email,
                    'phone'      => $phone,
                    'quantity'   => $quantity,
                    'amount'     => $amount,
                    'post_data'  => $post_data,
                    'mall_id'    => $mall_id,
                    'object_id'  => $object_id,
                ),
                array(
                    'first_name' => 'required',
                    'last_name'  => 'required',
                    'email'      => 'required',
                    'phone'      => 'required',
                    'quantity'   => 'required|orbit.allowed.quantity',
                    'amount'     => 'required',
                    'post_data'  => 'required',
                    'mall_id'    => 'required',
                    'object_id'  => 'required|orbit.exists.coupon',
                ),
                array(
                    'orbit.allowed.quantity' => 'REQUESTED_QUANTITY_NOT_AVAILABLE',
                    'orbit.allowed.per_user' => 'MAXIMUM_PURCHASE_REACHED',
                    'orbit.exists.coupon' => 'Coupon does not exists.',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Begin database transaction
            $this->beginTransaction();

            // Get coupon detail from DB.
            $coupon = Coupon::select('price_selling', 'promotions.promotion_id', 'promotion_type', DB::raw("m.country as coupon_country"))
                            ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('merchants as m', DB::raw("m.merchant_id"), '=', 'promotion_retailer.retailer_id')
                            ->findOrFail($object_id);

            // Validate currency being sent against coupon's currency.
            // If not equal, then abort request.
            // Old data will likely doesn't have currency,
            // so we need to assume it's IDR for now.
            // New data/after data migration, each paid coupon
            // should have currency set based on its location/country.
            $coupon->currency = 'IDR';
            if (! empty($coupon->coupon_country) && $coupon->coupon_country !== 'Indonesia') {
                $coupon->currency = 'SGD';
            }

            if ($currency !== $coupon->currency) {
                $errorMessage = 'Invalid currency!';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Get mall timezone
            $mallTimeZone = 'Asia/Jakarta';
            $mall = null;
            if ($mall_id !== 'gtm') {
                $mall = Mall::where('merchant_id', $mall_id)->first();
                if (!empty($mall)) {
                    $mallTimeZone = $mall->getTimezone($mall_id);
                }
            }

            $payment_new = new PaymentTransaction;
            $payment_new->user_email = $email;
            $payment_new->user_name = $user_name;
            $payment_new->user_id = $user_id;
            $payment_new->phone = $phone;
            $payment_new->country_id = $country_id;
            $payment_new->payment_method = 'stripe';
            $payment_new->amount = $quantity * $coupon->price_selling;
            $payment_new->currency = $currency;
            $payment_new->status = PaymentTransaction::STATUS_STARTING;
            $payment_new->timezone_name = $mallTimeZone;
            $payment_new->post_data = serialize($post_data);

            $payment_new->save();

            // Insert detail information
            $paymentDetail = new PaymentTransactionDetail;
            $paymentDetail->payment_transaction_id = $payment_new->payment_transaction_id;
            $paymentDetail->currency = $currency;
            $paymentDetail->price = $coupon->price_selling;
            $paymentDetail->quantity = $quantity;

            OrbitInput::post('object_id', function($object_id) use ($paymentDetail) {
                $paymentDetail->object_id = $object_id;
            });

            OrbitInput::post('object_type', function($object_type) use ($paymentDetail) {
                $paymentDetail->object_type = $object_type;
            });

            OrbitInput::post('object_name', function($object_name) use ($paymentDetail) {
                $paymentDetail->object_name = $object_name;
            });

            $paymentDetail->save();

            // Insert normal/paypro details
            $paymentDetailNormalPaypro = new PaymentTransactionDetailNormalPaypro;
            $paymentDetail->normal_paypro_detail()->save($paymentDetailNormalPaypro);

            // Link this payment to reserved coupons according to requested quantity.
            IssuedCoupon::where('user_id', $user_id)
                ->where('promotion_id', $object_id)
                ->where('transaction_id', NULL)
                ->where('status', IssuedCoupon::STATUS_RESERVED)
                ->skip(0)->take($quantity)
                ->update(['transaction_id' => $payment_new->payment_transaction_id]);

            // Commit the changes
            $this->commit();

            // TODO: Log activity
            $activity = Activity::mobileci()
                    ->setActivityType('transaction')
                    ->setUser($user)
                    ->setActivityName('transaction_status')
                    ->setActivityNameLong('Transaction is Starting')
                    ->setModuleName('Midtrans Transaction')
                    ->setObject($payment_new)
                    ->setNotes($coupon->promotion_type)
                    ->setLocation($mall)
                    ->responseOK()
                    ->save();

            $payment_new->quantity = $quantity;

            $this->response->data = $payment_new;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request OK';

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();

        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
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
            // Rollback the changes
            $this->rollBack();

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * Register custom validation.
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

            $coupon = Coupon::select('maximum_issued_coupon', 'max_quantity_per_user', 'max_quantity_per_purchase')
                              ->findOrFail($couponId);

            // Globally issued coupon count regardless of the Customer.
            $issuedCouponCount = IssuedCoupon::where('promotion_id', $couponId)
                                    ->whereIn('status', [
                                        IssuedCoupon::STATUS_ISSUED,
                                        IssuedCoupon::STATUS_REDEEMED,
                                        IssuedCoupon::STATUS_RESERVED
                                    ])->count();

            // Issued coupon count for current Customer.
            $userCouponCount = IssuedCoupon::where('user_id', $user->user_id)
                                            ->where('promotion_id', $couponId)
                                            ->count();

            // Get reserved coupon count (to make sure Customer reserve it before paying)
            $reservedCouponCount = IssuedCoupon::where('user_id', $user->user_id)
                                                ->where('promotion_id', $couponId)
                                                ->whereIn('status', [IssuedCoupon::STATUS_RESERVED])
                                                ->count();

            // We should ignore the requested quantity when checking for availability
            // from PaymentMidtransCreate.
            $issuedCouponCount -= $requestedQuantity;
            $issuedCouponCount = $issuedCouponCount < 0 ? 0 : $issuedCouponCount;

            $userCouponCount -= $requestedQuantity;
            $userCouponCount = $userCouponCount < 0 ? 0 : $userCouponCount;

            // If max_quantity in DB is empty, then assume it is old data.
            // We should fallback to value defined in config file.
            $maxQuantityPerPurchase = empty($coupon->max_quantity_per_purchase) ?
                                    Config::get('orbit.transaction.max_quantity_per_purchase', 1) :
                                    $coupon->max_quantity_per_purchase;

            $maxQuantityPerUser = empty($coupon->max_quantity_per_user) ? 9999 :
                                    $coupon->max_quantity_per_user;

            // Available coupon globally.
            $availableCoupon = $coupon->maximum_issued_coupon - $issuedCouponCount;

            // Available coupon for current Customer
            $availableCouponForUser = $maxQuantityPerUser - $userCouponCount;

            // Customer should be able to buy if requested quantity is:
            // - lower than available coupon (globally),
            // - lower than maximum quantity per purchase
            return $requestedQuantity <= $availableCoupon &&
                   $requestedQuantity <= $maxQuantityPerPurchase &&
                   $requestedQuantity <= $availableCouponForUser &&
                   $requestedQuantity <= $reservedCouponCount;
        });
    }
}
