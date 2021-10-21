<?php

namespace Orbit\Controller\API\v1\BrandProduct\Setting;

use DB;
use App;
use Lang;
use Order;
use Tenant;
use Config;
use StdClass;
use Exception;
use Validator;
use Carbon\Carbon;
use DominoPOS\OrbitACL\ACL;
use Orbit\Database\ObjectID;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\ControllerAPI;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\QueryException;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;

class SettingStoreUpdateAPIController extends ControllerAPI
{

    /**
     * Update setting store
     *
     * @author Kadek <kadek@dominopos.com>
     */
    public function postUpdate()
    {
        try {
            $httpCode = 200;
            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $userType = $user->user_type;
            $brandId = $user->base_merchant_id;
            $settingData = OrbitInput::post('setting_data');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'setting_data'  => $settingData,
                ),
                array(
                    'setting_data'  => 'required|orbit.validate.json',
                ),
                array(
                    'setting_data.required' => 'Setting required',
                    'orbit.validate.json'   => 'Invalid json format',
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Update the settings
            $updatedStores = $this->updateStoreSetting($settingData);

            // Commit the changes
            $this->commit();

            $this->response->data = $updatedStores;
        } catch (ACLForbiddenException $e) {
            // Rollback the changes
            $this->rollBack();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            // Rollback the changes
            $this->rollBack();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            // Rollback the changes
            $this->rollBack();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (\Exception $e) {
            // Rollback the changes
            $this->rollBack();
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Validate json format
        Validator::extend('orbit.validate.json', function ($attribute, $value, $parameters) {

            $settings = @json_decode($value, true);

            if (json_last_error() != JSON_ERROR_NONE) {
                return FALSE;
            }

            foreach($settings as $key => $value) {
                if (!isset($value['merchant_id']) || 
                    !isset($value['enable_reservation']) || 
                    !isset($value['enable_checkout'])) {
                        return FALSE;
                }
            }

            return TRUE;
        });
    }

    public function updateStoreSetting($data) 
    {
        $returnedData = [];
        $settings = @json_decode($data, true);

        foreach($settings as $key => $value) {
            $store = Tenant::where('merchant_id', '=', $value['merchant_id'])->first();
            if ($store) {
                $store->enable_reservation = $value['enable_reservation'];
                $store->enable_checkout = $value['enable_checkout'];
                $store->save();
    
                $data = new StdClass();
                $data->merchant_id = $store->merchant_id;
                $data->enable_reservation = $store->enable_reservation;
                $data->enable_checkout = $store->enable_checkout;
                $returnedData[] = $data;
            }
        }

        return $returnedData;
    }
}

