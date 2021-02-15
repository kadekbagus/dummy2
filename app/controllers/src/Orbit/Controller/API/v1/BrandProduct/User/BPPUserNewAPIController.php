<?php

namespace Orbit\Controller\API\v1\BrandProduct\User;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use stdclass;
use BppUser;
use DB;
use Exception;
use App;
use Hash;

class BPPUserNewAPIController extends ControllerAPI
{

    /**
     * Create new user on brand product portal.
     *
     * @author kadek <kadek@gotomalls.com>
     */
    public function postNewUser()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');

            $userId = $user->bpp_user_id;
            $userType = $user->user_type;
            $brandId = $user->base_merchant_id;

            $email = OrbitInput::post('email');
            $name = OrbitInput::post('name');
            $password = OrbitInput::post('password');
            $merchantId = OrbitInput::post('merchant_id');
            $status = OrbitInput::post('status', 'inactive');

            // Only brand allowed to create store users
            if ($userType === 'store') {
                $errorMessage = 'you are not allowed to create new user';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $validator = Validator::make(
                array(
                    'email'         => $email,
                    'name'          => $name,
                    'password'      => $password,
                    'merchant_id'   => $merchantId,
                    'status'        => $status,
                ),
                array(
                    'email'       => 'required|email|unique:bpp_users',
                    'name'        => 'required',
                    'password'    => 'required',
                    'merchant_id' => 'required',
                    'status'      => 'in:inactive,active',
                ),
                array(
                    'merchant_id.required'  => 'The store name field is required'
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $newBppUser = new BppUser();
            $newBppUser->name = $name;
            $newBppUser->email = $email;
            $newBppUser->password = Hash::make($password);
            $newBppUser->status = $status;
            $newBppUser->user_type = 'store';
            $newBppUser->base_merchant_id = $brandId;
            $newBppUser->merchant_id = is_array($merchantId)
                ? $merchantId[0] : $merchantId;
            $newBppUser->save();

            $newBppUser->stores()->attach($merchantId);

            // Commit the changes
            $this->commit();

            $this->response->data = $newBppUser;

        } catch (Exception $e) {
            // Rollback the changes
            $this->rollBack();
            return $this->handleException($e);
        }

        return $this->render();
    }

}
