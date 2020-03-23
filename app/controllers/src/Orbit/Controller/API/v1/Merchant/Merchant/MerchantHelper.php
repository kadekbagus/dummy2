<?php namespace Orbit\Controller\API\v1\Merchant\Merchant;
/**
 * Helpers for specific Merchant\Merchant Namespace
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
            $baseMerchant = BaseMerchant::with('baseMerchantTranslation', 'productTags')->excludeDeleted()
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

    /**
     * @param object $baseMerchant
     * @param string $translations_json_string
     * @throws InvalidArgsException
     */
    public function validateAndSaveTranslations($baseMerchant, $translations_json_string, $scenario = 'create')
    {

        /*
         * JSON structure: object with keys = merchant_language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where MerchantTranslation object is object with keys:
         *   description, ticket_header, ticket_footer.
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['description', 'custom_title', 'meta_description'];
        $operations = [];

        $data = @json_decode($translations_json_string);
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }

        foreach ($data as $language_id => $translations) {
            $language = Language::excludeDeleted()
                ->where('language_id', '=', $language_id)
                ->first();
            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
            }
            $existing_translation = BaseMerchantTranslation::
                  where('base_merchant_id', '=', $baseMerchant->base_merchant_id)
                ->where('language_id', '=', $language_id)
                ->first();

            if ($translations === null) {
                // deleting, verify exists
                if (empty($existing_translation)) {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
                }
                $operations[] = ['delete', $existing_translation];
            } else {
                foreach ($translations as $field => $value) {
                    if (!in_array($field, $valid_fields, TRUE)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.key'));
                    }
                    if ($value !== null && !is_string($value)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.value'));
                    }
                }
                if (empty($existing_translation)) {
                    $operations[] = ['create', $language_id, $translations];
                } else {
                    $operations[] = ['update', $existing_translation, $translations];
                }
            }
        }

        foreach ($operations as $operation) {
            $op = $operation[0];
            if ($op === 'create') {
                $newBaseMerchantTranslation = new BaseMerchantTranslation();
                $newBaseMerchantTranslation->base_merchant_id = $baseMerchant->base_merchant_id;
                $newBaseMerchantTranslation->language_id = $operation[1];
                $newBaseMerchantTranslation->description = $operation[2]->description;
                $newBaseMerchantTranslation->meta_description = $operation[2]->meta_description;
                $newBaseMerchantTranslation->custom_title = isset($operation[2]->custom_title) ? $operation[2]->custom_title : null;
                $newBaseMerchantTranslation->save();
                $baseMerchantTranslations[] = $newBaseMerchantTranslation;

                $baseMerchant->translations = $baseMerchantTranslations;
            }
            elseif ($op === 'update') {
                /** @var MerchantTranslation $existing_translation */
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->save();

                $baseMerchant->setRelation('translation_'. $existing_translation->language_id, $existing_translation);
            }
            elseif ($op === 'delete') {
                /** @var MerchantTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->delete();
            }
        }

        // to prevent error on saving base merchant
        unset($baseMerchant->translations);
    }
}
