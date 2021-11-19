<?php namespace Orbit\Controller\API\v1\Product\Setting;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use stdclass;
use Setting;
use Validator;

class SettingListAPIController extends ControllerAPI
{
	protected $allowedRoles = ['product manager'];

    /**
     * Show setting for pulsa and game voucher.
     *
     *
     * @return Illuminate\Http\Response
     */
    public function getList()
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
                          'bpjs_bill',
                          'internet_provider_bill',
                          'gtm_mdr_value'];

            $type = OrbitInput::get('type');

            $validator = Validator::make(
                array(
                    'type' => $type,
                ),
                array(
                    'type' => 'in:'.implode(",", $validType),
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
                'pdam_bill' => 'enable_bpjs_bill_page',
                'bpjs_bill' => 'enable_internet_provider_bill_page',
                'internet_provider_bill' => 'enable_internet_provider_bill_page',
                'gtm_mdr_value' => 'gtm_mdr_value',
            ];

            $setting = Setting::select('setting_name', 'setting_value')->whereIn('setting_name', $data);

            OrbitInput::get('type', function($type) use ($setting, $data)
            {
                $setting->where('setting_name', $data[$type]);
            });

            $_setting = clone $setting;

            $totalItems = RecordCounter::create($_setting)->count();
            $listOfItems = $setting->get();

            $data = new stdclass();
            $data->total_records = $totalItems;
            $data->returned_records = count($listOfItems);
            $data->records = $listOfItems;

            if ($totalItems === 0) {
                $data->records = NULL;
                $this->response->message = "There is no setting that matched your search criteria";
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
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
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render($httpCode);
    }
}
