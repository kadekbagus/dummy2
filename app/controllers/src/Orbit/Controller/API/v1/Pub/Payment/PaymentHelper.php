<?php namespace Orbit\Controller\API\v1\Pub\Payment;
/**
 * Helpers for specific LuckyDraw Namespace
 *
 */
use Validator;
use PaymentTransaction;
use Coupon;

use Config;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

class PaymentHelper
{
    protected $valid_language = NULL;

    /**
     * Static method to instantiate the class.
     */
    public static function create()
    {
        return new static();
    }

    public function registerCustomValidation() {
        // Check language is exists
        Validator::extend('orbit.exist.payment_transaction_id', function ($attribute, $value, $parameters) {

            $payment = PaymentTransaction::where('payment_transaction_id', '=', $value)
                                         ->first();

            if (empty($payment)) {
                return FALSE;
            }

            return TRUE;
        });

        Validator::extend('orbit.allowed.quantity', function ($attribute, $value, $parameters) {

            $maxQuantity = Config::get('orbit.transaction.max_quantity_per_purchase', 1);

            $couponId = OrbitInput::post('coupon_id');
            if (empty($couponId)) {
                $couponId = OrbitInput::post('object_id');
            }

            $coupon = Coupon::select('available')->findOrFail($couponId);

            if ($value <= $maxQuantity && $value <= $coupon->available) {
                return TRUE;
            }

            return FALSE;
        });

        Validator::extend('orbit.equals.total', function ($attribute, $value, $parameters) {

            $quantity = (double) OrbitInput::post($parameters[0], 1.00);
            $single_price = (double) OrbitInput::post($parameters[1], 0.00);

            if ($value == $quantity * $single_price) {
                return TRUE;
            }

            return FALSE;
        });
    }

    public function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    public function getValidLanguage()
    {
        return $this->valid_language;
    }
}