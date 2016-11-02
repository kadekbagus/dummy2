<?php namespace Orbit\Controller\API\v1\Merchant\Merchant;
/**
 * Helpers for specific Merchant\Merchant Namespace
 *
 */
use Validator;
use BaseMerchant;
use BaseMerchantTranslation;
use Category;
use App;

class MerchantHelper
{
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
    public function merchantCustomValidator()
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

        // Check existing merchant name
        Validator::extend('orbit.exist.merchant_name', function ($attribute, $value, $parameters) {
            $merchant = BaseMerchant::where('name', '=', $value)
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
    }

    /**
     * @param object $baseMerchant
     * @param string $translations_json_string
     * @throws InvalidArgsException
     */
    public function validateAndSaveTranslations($newBaseMerchant, $translations_json_string)
    {
        $data = @json_decode($translations_json_string);
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }
        foreach ($data as $language_id => $translations) {
            $newBaseMerchantTranslation = new BaseMerchantTranslation();
            $newBaseMerchantTranslation->base_merchant_id = $newBaseMerchant->base_merchant_id;
            $newBaseMerchantTranslation->language_id = $language_id;
            $newBaseMerchantTranslation->description = $translations->description;
            $newBaseMerchantTranslation->save();
            $baseMerchantTranslations[] = $newBaseMerchantTranslation;
        }
        $newBaseMerchant->translations = $baseMerchantTranslations;
    }
}
