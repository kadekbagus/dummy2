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
use DB;
use Validator;
use Lang;
use \Exception;
use \Event;
use Orbit\Controller\API\v1\Merchant\Store\StoreHelper;
use BaseStore;

class StoreNewAPIController extends ControllerAPI
{
    protected $newStoreRoles = ['merchant database admin'];
    /**
     * POST - post new store
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string base_merchant_id - The id of base merchant
     * @param string mall_id - The id of mall
     * @param string floor_id - The id of floor on the mall
     * @param string unit - The unit on the mall
     * @param string phone - The store phone
     * @param string status - The store status ('active' or 'inactive')
     * @param string verification_number - The verification number
     * @param file pictures - The store images (array)
     * @param file maps - The store map
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewStore()
    {
        $newstore = NULL;
        $user = NULL;
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->newStoreRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $base_merchant_id = OrbitInput::post('base_merchant_id');
            $mall_id = OrbitInput::post('mall_id');
            $floor_id = OrbitInput::post('floor_id');
            $unit = OrbitInput::post('unit');
            $phone = OrbitInput::post('phone');
            $status = OrbitInput::post('status', 'active');
            $verification_number = OrbitInput::post('verification_number');
            //images and map
            $images = OrbitInput::files('pictures');
            $map = OrbitInput::files('maps');
            $grab_images = OrbitInput::files('grab_pictures');

            $storeHelper = StoreHelper::create();
            $storeHelper->storeCustomValidator();

            // generate array validation image
            $images_validation = $storeHelper->generate_validation_image('store_image', $images, 'orbit.upload.retailer.picture', 3);
            $map_validation = $storeHelper->generate_validation_image('store_map', $map, 'orbit.upload.retailer.map');
            $images_validation = $storeHelper->generate_validation_image('store_image_3rd_party_coupon', $grab_images, 'orbit.upload.base_store.grab_picture', 3);

            $validation_data = [
                'base_merchant_id'    => $base_merchant_id,
                'mall_id'             => $mall_id,
                'floor_id'            => $floor_id,
                'status'              => $status,
                'verification_number' => $verification_number,
            ];

            $validation_error = [
                'base_merchant_id'    => 'required|orbit.empty.base_merchant',
                'mall_id'             => 'required|orbit.empty.mall|orbit.mall.country:' . $base_merchant_id,
                'floor_id'            => 'orbit.empty.floor:' . $mall_id,
                'status'              => 'in:active,inactive',
                'verification_number' => 'alpha_num|orbit.unique.verification_number:' . $mall_id . ',' . '',
            ];

            $validation_error_message = [
                'orbit.mall.country' => 'Mall does not exist in your selected country'
            ];

            // unit make floor_id is required
            if (! empty($unit)) {
                $validation_error['floor_id'] = 'required|orbit.empty.floor:' . $mall_id;
            }

            // add validation images
            if (! empty($images_validation)) {
                $validation_data += $images_validation['data'];
                $validation_error += $images_validation['error'];
                $validation_error_message += $images_validation['error_message'];
            }
            // add validation map
            if (! empty($map_validation)) {
                $validation_data += $map_validation['data'];
                $validation_error += $map_validation['error'];
                $validation_error_message += $map_validation['error_message'];
            }

            $validator = Validator::make($validation_data, $validation_error, $validation_error_message);

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $newstore = new BaseStore();
            $newstore->base_merchant_id = $base_merchant_id;
            $newstore->merchant_id = $mall_id;
            $newstore->floor_id = $floor_id;
            $newstore->unit = $unit;
            $newstore->phone = $phone;
            $newstore->status = $status;
            $newstore->verification_number = $verification_number;
            $newstore->save();

            // cause not required
            $newstore->floor = '';
            if (! empty($floor_id) || $floor_id !== '') {
                $newstore->floor = $storeHelper->getValidFloor()->object_name;
            }

            $newstore->mall_id = $mall_id;
            $newstore->location = $storeHelper->getValidMall()->name;

            Event::fire('orbit.basestore.postnewstore.after.save', array($this, $newstore));
            $this->response->data = $newstore;

            // Commit the changes
            $this->commit();
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
