<?php namespace Orbit\Controller\API\v1\Merchant\Store;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Lang;
use \Exception;
use Orbit\Controller\API\v1\Merchant\Store\StoreHelper;
use BaseStore;

class StoreNewAPIController extends ControllerAPI
{
    /**
     * POST - post new store
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewStore()
    {
        $store = NULL;
        $user = NULL;
        $httpCode = 200;
        try {
            $base_merchant_id = OrbitInput::post('base_merchant_id');
            $merchant_id = OrbitInput::post('merchant_id');
            $floor_id = OrbitInput::post('floor_id');
            $unit = OrbitInput::post('unit');
            $phone = OrbitInput::post('phone');
            $status = OrbitInput::post('status', 'active');
            $verification_number = OrbitInput::post('verification_number');

            $storeHelper = StoreHelper::create();
            $storeHelper->storeCustomValidator();

            $validator = Validator::make(
                array(
                    'base_merchant_id'   => $base_merchant_id,
                    'merchant_id'   => $merchant_id,
                    'floor_id'   => $floor_id,
                    'status'   => $status,
                ),
                array(
                    'base_merchant_id'   => 'required|orbit.empty.base_merchant',
                    'merchant_id'   => 'required|orbit.empty.mall',
                    'floor_id'   => 'orbit.empty.floor:' . $merchant_id,
                    'status'   => 'in:active,inactive',
                ),
                array(
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $store = new BaseStore();
            $store->base_merchant_id = $base_merchant_id;
            $store->merchant_id = $merchant_id;
            $store->floor_id = $floor_id;
            $store->unit = $unit;
            $store->phone = $phone;
            $store->status = $status;
            $store->verification_number = $verification_number;
            $store->save();

            $this->response->data = $store;
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
            $this->response->data = null;

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

            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }
}
