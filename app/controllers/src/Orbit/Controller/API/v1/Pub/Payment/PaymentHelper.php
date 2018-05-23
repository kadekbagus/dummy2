<?php namespace Orbit\Controller\API\v1\Pub\Payment;
/**
 * Helpers for specific LuckyDraw Namespace
 *
 */
use Validator;
use PaymentTransaction;

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
                                        ->whereIn('status', ['starting', 'pending'])
                                        ->first();

            if (empty($payment)) {
                return FALSE;
            }

            return TRUE;
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