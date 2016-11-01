<?php namespace Orbit\Controller\API\v1\Merchant\Store;
/**
 * Helpers for specific Store Namespace
 *
 */
use Validator;
use DB;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use BaseStore;
use BaseMerchant;
use Mall;
use Object;

class StoreHelper
{
    protected $valid_base_merchant = NULL;
    protected $valid_mall = NULL;
    protected $valid_floor = NULL;
    /**
     * Static method to instantiate the class.
     */
    public static function create()
    {
        return new static();
    }

    public function storeCustomValidator() {
        Validator::extend('orbit.empty.base_merchant', function ($attribute, $value, $parameters) {
            $base_merchant = BaseMerchant::excludeDeleted()
                        ->where('base_merchant_id', $value)
                        ->first();

            if (empty($base_merchant)) {
                return FALSE;
            }

            $valid_base_merchant = $base_merchant;
            return TRUE;
        });

        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->where('object_type', 'mall')
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            $valid_mall = $mall;
            return TRUE;
        });

        Validator::extend('orbit.empty.floor', function ($attribute, $value, $parameters) {
            $mall_id = $parameters[0];
            $floor = Object::excludeDeleted()
                        ->where('object_id', $value)
                        ->where('object_type', 'floor')
                        ->where('merchant_id', $mall_id)
                        ->first();

            if (empty($floor)) {
                return FALSE;
            }

            $valid_floor = $floor;
            return TRUE;
        });
    }
}