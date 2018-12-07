<?php namespace Orbit\Controller\API\v1\Article;
/**
 * Helpers for specific Article Namespace
 *
 */
use OrbitShop\API\v1\OrbitShopAPI;
use Validator;
use BaseMerchant;
use BaseStore;
use BaseMerchantTranslation;
use Category;
use App;
use Language;
use Lang;

class ArticleHelper
{
    /**
     * Static method to instantiate the class.
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Custom validator used in Orbit\Controller\API\v1\Article namespace
     *
     */
    public function articleCustomValidator()
    {
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

        // Check country in existing store
        Validator::extend('orbit.store.country', function ($attribute, $value, $parameters) {
            $baseMerchantId = $parameters[0];
            $countryId = $parameters[1];

            //Cannot change country if there is any merchant linked to store
            $baseMerchants = BaseMerchant::where('base_merchant_id', $baseMerchantId)
                            ->first();

            $merchants = BaseStore::join('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
                            ->where('base_merchants.country_id', '=', $baseMerchants->country_id)
                            ->where('base_stores.base_merchant_id', '=', $baseMerchantId)
                            ->first();

            if ($baseMerchants->country_id != $countryId && ! empty($merchants)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check supported language
        Validator::extend('orbit.supported.language', function ($attribute, $value, $parameters) {
            $lang = Language::where('name', '=', $value)->where('status', '=', 'active')->first();

            if (empty($lang)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check store default language
        Validator::extend('orbit.store.language', function ($attribute, $value, $parameters) {
            $baseMerchantId = $parameters[0];
            $mobileDefaultLanguage = $parameters[1];

            //Cannot change country if there is any merchant linked to store
            $baseMerchants = BaseMerchant::where('base_merchant_id', $baseMerchantId)
                            ->first();

            $merchants = BaseStore::join('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
                            ->where('base_merchants.mobile_default_language', '=', $baseMerchants->mobile_default_language)
                            ->where('base_stores.base_merchant_id', '=', $baseMerchantId)
                            ->first();

            if ($baseMerchants->mobile_default_language != $mobileDefaultLanguage && ! empty($merchants)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check existing merchant name
        Validator::extend('orbit.exist.merchant_name', function ($attribute, $value, $parameters) {
            $country = $parameters[0];
            $merchant = BaseMerchant::where('name', '=', $value)
                            ->where('country_id', $country)
                            ->first();

            if (! empty($merchant)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the validity of URL
        Validator::extend('orbit.formaterror.url.web', function ($attribute, $value, $parameters) {
            $url = 'http://' . $value;

            $pattern = '@^((http:\/\/www\.)|(www\.)|(http:\/\/))[a-zA-Z0-9._-]+\.[a-zA-Z.]{2,5}$@';

            if (! preg_match($pattern, $url)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existance of category id
        Validator::extend('orbit.empty.category', function ($attribute, $value, $parameters) {
            $category = Category::excludeDeleted()
                                ->where('category_id', $value)
                                ->first();

            if (empty($category)) {
                return FALSE;
            }

            App::instance('orbit.empty.category', $category);

            return TRUE;
        });

        // Check the images, we are allowed array of images but not more that one
        Validator::extend('nomore.than.one', function ($attribute, $value, $parameters) {
            if (is_array($value['name']) && count($value['name']) > 1) {
                return FALSE;
            }

            return TRUE;
        });
    }
}