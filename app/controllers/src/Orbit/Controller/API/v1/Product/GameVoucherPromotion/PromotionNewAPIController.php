<?php

namespace Orbit\Controller\API\v1\Product\GameVoucherPromotion;

use Exception;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use GameVoucherPromotion;
use GameVoucherPromotionDetail;
use ProviderProduct;
use Validator;
use Input;
use SplFileObject;

/**
 * Create a new Game Voucher Promotion.
 *
 * @author ahmad <ahmad@gotomalls.com>
 */
class PromotionNewAPIController extends ControllerAPI
{
    protected $validRoles = ['product manager'];

    public function postNew ()
    {
        $user = NULL;
        try {
            $httpCode = 200;

            $this->checkAuth();

            $user = $this->api->user;

            $role = $user->role;
            $validRoles = $this->validRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $this->registerCustomValidation();

            $validation_data = [
                'campaign_name' => OrbitInput::post('campaign_name'),
                'start_date' => OrbitInput::post('start_date'),
                'end_date' => OrbitInput::post('end_date'),
                'status' => OrbitInput::post('status'),
                'provider_product_id' => OrbitInput::post('provider_product_id'),
                'file' => Input::file('file'),
            ];

            $validation_error = [
                'campaign_name' => 'required',
                'start_date' => 'required',
                'end_date' => 'required',
                'status' => 'required|in:active,inactive',
                'provider_product_id' => 'required|orbit.provider_product_id',
                'file' => 'required',
            ];

            $validation_error_message = [
                'orbit.provider_product_id' => 'Provider product is not found'
            ];

            $validator = Validator::make(
                $validation_data,
                $validation_error,
                $validation_error_message
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $item = new GameVoucherPromotion();
            $item->campaign_name = OrbitInput::post('campaign_name');
            $item->start_date = OrbitInput::post('start_date');
            $item->end_date = OrbitInput::post('end_date');
            $item->status = OrbitInput::post('status');
            $item->provider_product_id = OrbitInput::post('provider_product_id');
            $item->save();

            // read csv file
            $csvInput = Input::file('file');
            $file = new SplFileObject($csvInput);
            $file->setFlags(SplFileObject::READ_CSV);
            $file->setCsvControl(',', '"', '\\'); // this is the default anyway though
            foreach ($file as $row) {
                list ($pinNumber, $serialNumber) = $row;
                $detail = new GameVoucherPromotionDetail();
                $detail->game_voucher_promotion_id = $item->game_voucher_promotion_id;
                $detail->pin_number = $pinNumber;
                $detail->serial_number = $serialNumber;
                $detail->save();
            }

            // Commit the changes
            $this->commit();

            $this->response->data = $item;

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
            if (\Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        Validator::extend('orbit.provider_product_id', function ($attribute, $value, $parameters) {
            $existing = ProviderProduct::where('provider_product_id', $value)->first();

            if (is_object($existing)) {
                return true;
            }

            return false;
        });
    }
}
