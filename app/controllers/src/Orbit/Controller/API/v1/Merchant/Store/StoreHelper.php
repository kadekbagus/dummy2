<?php namespace Orbit\Controller\API\v1\Merchant\Store;
/**
 * Helpers for specific Store Namespace
 *
 */
use Validator;
use DB;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use DominoPOS\OrbitUploader\Uploader as OrbitUploader;
use Config;
use Lang;
use BaseStore;
use BaseMerchant;
use Mall;
use Object;
use Tenant;
use UserVerificationNumber;
use User;
use \Carbon\Carbon;
use CampaignLocation;
use CouponRetailer;
use CouponRetailerRedeem;
use NewsMerchant;
use BaseStoreTranslation;
use Language;

class StoreHelper
{
    protected $valid_base_merchant = NULL;
    protected $valid_base_store = NULL;
    protected $valid_mall = NULL;
    protected $valid_floor = NULL;
    /**
     * Static method to instantiate the class.
     */
    public static function create()
    {
        return new static();
    }

    public function generate_validation_image($image_name, $images, $config, $max_count = 1) {
        $validation = [];
        if (! empty($images)) {
            $images_properties = OrbitUploader::simplifyFilesVar($images);
            $image_config = Config::get($config);
            $image_type =  "image/" . implode(",image/", $image_config['file_type']);
            $image_units = OrbitUploader::bytesToUnits($image_config['file_size']);

            $validation['data'] = [
                $image_name => $images
            ];
            $validation['error'] = [
                $image_name => 'nomore.than:' . $max_count
            ];
            $validation['error_message'] = [
                $image_name . '.nomore.than' => Lang::get('validation.max.array', array('max' => $max_count))
            ];

            foreach ($images_properties as $idx => $image) {
                $ext = strtolower(substr(strrchr($image->name, '.'), 1));
                $idx+=1;

                $validation['data'][$image_name . '_type_' . $idx] = $image->type;
                $validation['data'][$image_name . '_' . $idx . '_size'] = $image->size;

                $validation['error'][$image_name . '_type_' . $idx] = 'in:' . $image_type;
                $validation['error'][$image_name . '_' . $idx . '_size'] = 'orbit.file.max_size:' . $image_config['file_size'];

                $validation['error_message'][$image_name . '_type_' . $idx . '.in'] = Lang::get('validation.orbit.file.type', array('ext' => $ext));
                $validation['error_message'][$image_name . '_' . $idx . '_size' . '.orbit.file.max_size'] = Lang::get('validation.orbit.file.max_size', array('name' => $image_config['name'], 'size' => $image_units['newsize'], 'unit' => $image_units['unit']));
            }
        }

        return $validation;
    }

    public function storeCustomValidator() {
        // exist base merchant
        Validator::extend('orbit.empty.base_merchant', function ($attribute, $value, $parameters) {
            $base_merchant = BaseMerchant::excludeDeleted()
                        ->where('base_merchant_id', $value)
                        ->first();

            if (empty($base_merchant)) {
                return FALSE;
            }

            $this->valid_base_merchant = $base_merchant;
            return TRUE;
        });

        // linked to pmp account
        Validator::extend('orbit.check_link.pmp_account', function ($attribute, $value, $parameters) {
            $baseStore = BaseStore::where('base_store_id', $parameters[0])
                ->first();

            if (! is_object($baseStore)) {
                return TRUE;
            }

            if ($baseStore->status === 'active' && $value === 'inactive') {
                $pmpAccount = User::leftJoin('user_merchant', 'user_merchant.user_id', '=', 'users.user_id')
                        ->leftJoin('base_stores', 'base_stores.base_store_id', '=', 'user_merchant.merchant_id')
                        ->where('users.status', 'active')
                        ->where('base_store_id', $parameters[0])
                        ->first();

                if (is_object($pmpAccount)) {
                    return FALSE;
                }
            }

            return TRUE;
        });

        // linked to active campaign
        Validator::extend('orbit.check_link.active_campaign', function ($attribute, $value, $parameters) {
            $tenant = Tenant::where('merchant_id', $parameters[0])
                ->first();

            // the BaseStore is not synced yet
            if (! is_object($tenant)) {
                return TRUE;
            }

            if ($tenant->status === 'active' && $value === 'inactive') {
                $campaignLocation = CampaignLocation::select('parent_id')->where('merchant_id', '=', $tenant->merchant_id)->first();

                $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
                    ->where('merchants.merchant_id','=', $campaignLocation->parent_id)
                    ->first();

                $timezoneName = $timezone->timezone_name;

                $nowMall = Carbon::now($timezoneName);

                $prefix = DB::getTablePrefix();

                $coupon = CouponRetailer::leftjoin('promotions', 'promotions.promotion_id', '=', 'promotion_retailer.promotion_id')
                        ->leftjoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                        ->whereRaw("(CASE WHEN {$prefix}promotions.end_date < {$this->quote($nowMall)} THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) NOT IN ('stopped', 'expired')")
                        ->where('promotion_retailer.retailer_id', $tenant->merchant_id)
                        ->first();

                if (is_object($coupon)) {
                    return FALSE;
                }

                $couponRedeem = CouponRetailerRedeem::leftjoin('promotions', 'promotions.promotion_id', '=', 'promotion_retailer_redeem.promotion_id')
                        ->leftjoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                        ->whereRaw("(CASE WHEN {$prefix}promotions.end_date < {$this->quote($nowMall)} THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) NOT IN ('stopped', 'expired')")
                        ->where('promotion_retailer_redeem.retailer_id', $tenant->merchant_id)
                        ->first();

                if (is_object($couponRedeem)) {
                    return FALSE;
                }

                $promotionNews = NewsMerchant::leftJoin('news', 'news_merchant.news_id', '=', 'news.news_id')
                            ->leftjoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                            ->whereRaw("(CASE WHEN {$prefix}news.end_date < {$this->quote($nowMall)} THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) NOT IN ('stopped', 'expired')")
                            ->where('news_merchant.merchant_id', $tenant->merchant_id)
                            ->first();

                if (is_object($promotionNews)) {
                    return FALSE;
                }
            }

            return TRUE;
        });

        // exist base store
        Validator::extend('orbit.empty.base_store', function ($attribute, $value, $parameters) {
            $prefix = DB::getTablePrefix();
            $base_store = BaseStore::excludeDeleted('base_stores')
                            ->join('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
                            ->selectRaw("{$prefix}base_stores.*, {$prefix}base_merchants.base_merchant_id,
                                {$prefix}base_merchants.name, {$prefix}base_merchants.name, {$prefix}base_merchants.description")
                            ->where('base_store_id', $value)
                            ->first();

            if (empty($base_store)) {
                return FALSE;
            }

            $this->valid_base_store = $base_store;
            return TRUE;
        });

        // duplicate base store
        Validator::extend('orbit.exists.base_store', function ($attribute, $value, $parameters) {
            $base_store_id = $parameters[0];
            $mall_id = $parameters[1];
            $floor_id = $parameters[2];
            $unit = $value;

            $base_store = BaseStore::excludeDeleted()
                           ->where('merchant_id', $mall_id)
                           ->where('floor_id', $floor_id)
                           ->where('unit', $unit)
                           ->first();

            if (! empty($base_store) && $base_store->base_store_id !== $base_store_id) {
                return FALSE;
            }

            return TRUE;
        });

        // exist mall
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->where('object_type', 'mall')
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            $this->valid_mall = $mall;
            return TRUE;
        });

        // exist floor
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

            $this->valid_floor = $floor;
            return TRUE;
        });

        Validator::extend('orbit.file.max_size', function ($attribute, $value, $parameters) {
            $config_size = $parameters[0];
            $file_size = $value;

            if ($file_size > $config_size) {
                return false;
            }

            return true;
        });

        // Check the images, we are allowed array of images but not more than
        Validator::extend('nomore.than', function ($attribute, $value, $parameters) {
            $max_count = $parameters[0];

            if (is_array($value['name']) && count($value['name']) > $max_count) {
                return FALSE;
            }

            return TRUE;
        });

        // Check if the merchant verification number is unique
        Validator::extend('orbit.unique.verification_number', function ($attribute, $value, $parameters) {
            // Current Mall
            $mall_id       = $parameters[0];
            $base_store_id = $parameters[1];

            // Check the base store which has verification number posted
            $baseStoreVerificationNumber = BaseStore::excludeDeleted()
                    ->where('verification_number', $value)
                    ->where('merchant_id', $mall_id)
                    ->first();

            // Check the tenant which has verification number posted
            $tenantVerificationNumber = Tenant::excludeDeleted()
                    ->where('object_type', 'tenant')
                    ->where('masterbox_number', $value)
                    ->where('parent_id', $mall_id)
                    ->first();

            // Check verification number tenant with cs verification number
            $csVerificationNumber = UserVerificationNumber::where('verification_number', $value)
                    ->where('merchant_id', $mall_id)
                    ->first();

            if ((! empty($tenantVerificationNumber) && $tenantVerificationNumber->merchant_id !== $base_store_id) || (! empty($baseStoreVerificationNumber) && $baseStoreVerificationNumber->base_store_id !== $base_store_id) || ! empty($csVerificationNumber)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check mall country
        Validator::extend('orbit.mall.country', function ($attribute, $value, $parameters) {
            $baseMerchantId = $parameters[0];

            $baseMerchants = BaseMerchant::where('base_merchant_id', $baseMerchantId)
                            ->first();

            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->where('country_id', $baseMerchants->country_id)
                        ->where('object_type', 'mall')
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

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

    public function getValidBaseStore()
    {
        return $this->valid_base_store;
    }

    public function getValidBaseMerchant()
    {
        return $this->valid_base_merchant;
    }

    public function getValidMall()
    {
        return $this->valid_mall;
    }

    public function getValidFloor()
    {
        return $this->valid_floor;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    /**
     * @param object $baseMerchant
     * @param string $translations_json_string
     * @throws InvalidArgsException
     */
    public function validateAndSaveTranslations($baseStore, $translations_json_string, $scenario = 'create')
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
            $existing_translation = BaseStoreTranslation::
                  where('base_store_id', '=', $baseStore->base_store_id)
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
                    if ($language->status == 'active') {
                        if (!in_array($field, $valid_fields, TRUE)) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.key'));
                        }
                        if ($value !== null && !is_string($value)) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.value'));
                        }
                        if ($field=='description' && $value == '') {
                            OrbitShopAPI::throwInvalidArgument('Description field is required');
                        }
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
                $newBaseStoreTranslation = new BaseStoreTranslation();
                $newBaseStoreTranslation->base_store_id = $baseStore->base_store_id;
                $newBaseStoreTranslation->language_id = $operation[1];
                $newBaseStoreTranslation->description = $operation[2]->description;
                $newBaseStoreTranslation->meta_description = isset($operation[2]->meta_description) ? $operation[2]->meta_description : null;
                $newBaseStoreTranslation->custom_title = isset($operation[2]->custom_title) ? $operation[2]->custom_title : null;
                $newBaseStoreTranslation->save();
                $baseStoreTranslations[] = $newBaseStoreTranslation;

                $baseStore->translations = $baseStoreTranslations;
            }
            elseif ($op === 'update') {
                /** @var MerchantTranslation $existing_translation */
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->save();

                $baseStore->setRelation('translation_'. $existing_translation->language_id, $existing_translation);
            }
            elseif ($op === 'delete') {
                /** @var MerchantTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->delete();
            }
        }

        // to prevent error on saving base merchant
        unset($baseStore->translations);
    }
}
