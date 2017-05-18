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
use Mall;
use Object;

class StoreUpdateAPIController extends ControllerAPI
{
    protected $updateStoreRoles = ['merchant database admin'];
    /**
     * POST - post update store
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string base_store_id - The id of base store
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
    public function postUpdateStore()
    {
        $updatestore = NULL;
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
            $validRoles = $this->updateStoreRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $base_store_id = OrbitInput::post('base_store_id');
            $base_merchant_id = OrbitInput::post('base_merchant_id');
            $mall_id = OrbitInput::post('mall_id');
            $floor_id = OrbitInput::post('floor_id', '');
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
                'base_store_id'       => $base_store_id,
                'base_merchant_id'    => $base_merchant_id,
                'mall_id'             => $mall_id,
                'floor_id'            => $floor_id,
                'status'              => $status,
                'verification_number' => $verification_number,
            ];

            $validation_error = [
                'base_store_id'       => 'required|orbit.empty.base_store',
                'base_merchant_id'    => 'required|orbit.empty.base_merchant',
                'mall_id'             => 'required|orbit.empty.mall|orbit.mall.country:' . $base_merchant_id,
                'floor_id'            => 'orbit.empty.floor:' . $mall_id,
                'status'              => 'in:active,inactive|orbit.check_link.pmp_account:' . $base_store_id . '|orbit.check_link.active_campaign:' . $base_store_id,
                'verification_number' => 'alpha_num|orbit.unique.verification_number:' . $mall_id . ',' . $base_store_id,
            ];

            $validation_error_message = [
                'orbit.mall.country' => 'Mall does not exist in your selected country',
                'orbit.check_link.pmp_account' => 'Store is linked to active PMP Account',
                'orbit.check_link.active_campaign' => 'Store is linked to active campaign',
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

            $updatestore = $storeHelper->getValidBaseStore();

            OrbitInput::post('mall_id', function($mall_id) use ($updatestore) {
                $updatestore->merchant_id = $mall_id;
            });

            OrbitInput::post('floor_id', function($floor_id) use ($updatestore) {
                $updatestore->floor_id = $floor_id;
            });

            OrbitInput::post('unit', function($unit) use ($updatestore) {
                $updatestore->unit = $unit;
            });

            OrbitInput::post('phone', function($phone) use ($updatestore) {
                $updatestore->phone = $phone;
            });

            OrbitInput::post('status', function($status) use ($updatestore) {
                $updatestore->status = $status;
            });

            OrbitInput::post('verification_number', function($verification_number) use ($updatestore) {
                $updatestore->verification_number = $verification_number;
            });

            $updatestore->save();

            $updatestore->mall_id = $mall_id;
            $updatestore->location = $storeHelper->getValidMall()->name;

            // cause not required
            if (! empty($floor_id) || $floor_id !== '') {
                $updatestore->floor = $storeHelper->getValidFloor()->object_name;
            } else {
                $floor = Object::excludeDeleted()
                            ->where('merchant_id', $updatestore->merchant_id)
                            ->where('object_id', $updatestore->floor_id)
                            ->first();

                if (empty($floor)) {
                    $updatestore->floor = '';
                } else {
                    $updatestore->floor = $floor->object_name;
                }
            }


            Event::fire('orbit.basestore.postupdatestore.after.save', array($this, $updatestore));
            $this->response->data = $updatestore;

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
