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

class StoreHelper
{

    /**
     * Static method to instantiate the class.
     */
    public static function create()
    {
        return new static();
    }

    public function storeCustomValidator() {

    }
}