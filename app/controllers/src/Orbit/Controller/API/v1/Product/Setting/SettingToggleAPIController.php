<?php namespace Orbit\Controller\API\v1\Product\Setting;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use stdclass;
use Setting;
use Validator;

class SettingToggleAPIController extends ControllerAPI
{
	protected $allowedRoles = ['product manager'];

    /**
     * Show setting for pulsa and game voucher.
     *
     *
     * @return Illuminate\Http\Response
     */
    public function postToggle()
    {
        $httpCode = 200;

        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->allowedRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $validType = ['pulsa',
                          'game_voucher',
                          'electricity',
                          'electricity_bill',
                          'pdam_bill',
                          'pbb_tax',
                          'bpjs_bill',
                          'internet_provider_bill',
                          'gtm_mdr_value'];

            $type = OrbitInput::post('type');
            $settingValue = OrbitInput::post('setting_value');

            $validator = Validator::make(
                array(
                    'type' => $type,
                ),
                array(
                    'type' => 'required|in:'.implode(",", $validType),
                ),
                array(
                    'type.in' => 'The argument you specified is not valid, the valid values are: '.implode(",", $validType),
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $data = [
                'pulsa' => 'enable_pulsa_page',
                'game_voucher' => 'enable_game_voucher_page',
                'electricity' => 'enable_electricity_page',
                'electricity_bill' => 'enable_electricity_bill_page',
                'pdam_bill' => 'enable_pdam_bill_page',
                'pbb_tax' => 'enable_pbb_tax_page',
                'bpjs_bill' => 'enable_bpjs_bill_page',
                'internet_provider_bill' => 'enable_internet_provider_bill_page',
                'gtm_mdr_value' => 'gtm_mdr_value',
            ];

            $setting = Setting::where('setting_name', $data[$type])->first();

            if (!is_object($setting)) {
                $errorMessage = 'setting not found';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $setting->setting_value = ($setting->setting_value === '1') ? 0 : 1;
            // Set value for gtm_mdr_value
            if ($type === 'gtm_mdr_value') {
                $setting->setting_value = $settingValue;
            }
            $setting->modified_by = $user->user_id;
            $setting->save();

            $this->response->data = $setting;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
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
            // Rollback the changes
            $this->rollBack();
        } catch (\Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            // Rollback the changes
            $this->rollBack();
        }
        return $this->render($httpCode);
    }
}
