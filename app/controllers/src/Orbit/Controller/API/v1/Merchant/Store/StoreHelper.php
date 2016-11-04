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

        // exist base store
        Validator::extend('orbit.empty.base_store', function ($attribute, $value, $parameters) {
            $base_store = BaseStore::excludeDeleted('base_stores')
                            ->join('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
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
}