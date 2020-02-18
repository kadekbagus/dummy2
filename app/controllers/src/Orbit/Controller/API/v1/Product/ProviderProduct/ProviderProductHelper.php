<?php namespace Orbit\Controller\API\v1\Product\ProviderProduct;
/**
 * Helpers for specific ProviderProduct Namespace
 *
 */
use OrbitShop\API\v1\OrbitShopAPI;
use Validator;
use App;
use Lang;
use ProviderProduct;

class ProviderProductHelper
{

    /**
     * Static method to instantiate the class.
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Custom validator used in Orbit\Controller\API\v1\Product\ProviderProduct namespace
     *
     */
    public function providerProductCustomValidator()
    {
        // Check existing code
        Validator::extend('orbit.exist.code', function ($attribute, $value, $parameters) {
            $provider_product = ProviderProduct::where('code', '=', $value)
                        ->first();

            if (! empty($provider_product)) {
                return FALSE;
            }

            return TRUE;
        });

        Validator::extend('orbit.exist.code_but_me', function ($attribute, $value, $parameters) {
            $provider_product_id = $parameters[0];
            $provider_product = ProviderProduct::where('code', '=', $value)
                                            ->where('provider_product_id', '!=', $provider_product_id)
                                            ->first();

            if (! empty($provider_product)) {
                return FALSE;
            }

            return TRUE;
        });

    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}