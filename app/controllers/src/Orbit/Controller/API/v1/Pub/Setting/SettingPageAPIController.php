<?php namespace Orbit\Controller\API\v1\Pub\Setting;
/**
 * API for getting page setting
 * @author kadek <kadek@dominopos.com>
 */
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Helper\MCash\API\Bill;
use Setting;
use Validator;
use stdclass;

class SettingPageAPIController extends PubControllerAPI
{
    /**
     * GET - Page Setting for Pulsa and Game Voucher
     *
     * @author kadek <kadek@dominopos.com>
     *
     * @param string `type` (required) - pulsa or game_voucher
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSetting()
    {
        $httpCode = 200;

        try {
            $user = $this->getUser();
            $type = OrbitInput::get('type');
            $billType = array_merge(
                [
                    'all',
                    'pulsa',
                    'game_voucher',
                    'electricity',
                ],
                Bill::getBillTypeIds()
            );

            $validator = Validator::make(
                array(
                    'type' => $type,
                ),
                array(
                    'type' => 'required|in:' . join(',', $billType),
                ),
                array(
                    'type.in' => 'The argument you specified is not valid, the valid values are: ' . join(',', $billType),
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $data = array_merge([
                'pulsa' => 'enable_pulsa_page',
                'game_voucher' => 'enable_game_voucher_page',
                'electricity' => 'enable_electricity_page',
            ], Bill::getBillSettingName());

            $setting = Setting::select('setting_name', 'setting_value');
            if ($type === 'all') {
                $setting = $setting->whereIn('setting_name', $data)->get();
            }
            else {
                $setting = $setting->where('setting_name', $data[$type])->first();
            }

            if (!is_object($setting)) {
                $data->records = NULL;
                $this->response->message = "There is no setting that matched your search criteria";
            }

            $this->response->data = $setting;
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
