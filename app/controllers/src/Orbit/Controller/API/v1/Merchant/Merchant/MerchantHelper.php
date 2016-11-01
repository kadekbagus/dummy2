<?php namespace Orbit\Controller\API\v1\Merchant\Merchant;
/**
 * Helpers for specific Merchant\Merchant Namespace
 *
 */
use Validator;
use Language;
use BaseMerchant;
use App;

class MerchantHelper
{
    protected $valid_language = NULL;

    /**
     * Static method to instantiate the class.
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Custom validator used in Orbit\Controller\API\v1\Merchant\Merchant namespace
     *
     */
    public function merchantCustomValidator() {
        // Check the existance of base merchant id
        Validator::extend('orbit.empty.base_merchant', function ($attribute, $value, $parameters) {
            $baseMerchant = BaseMerchant::excludeDeleted()
                                   ->where('base_merchant_id', $value)
                                   ->first();

            if (empty($baseMerchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.base_merchant', $baseMerchant);

            return TRUE;
        });
    }
}
