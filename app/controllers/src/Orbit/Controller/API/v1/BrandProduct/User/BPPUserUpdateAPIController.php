<?php

namespace Orbit\Controller\API\v1\BrandProduct\User;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use App;
use Hash;
use Validator;
use stdclass;
use BppUser;
use Exception;

class BPPUserUpdateAPIController extends ControllerAPI
{

    /**
     * Update user on brand product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postUpdateUser()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');

            $userId = $user->bpp_user_id;
            $userType = $user->user_type;
            $brandId = $user->base_merchant_id;

            $bppUserId = OrbitInput::post('bpp_user_id', null);
            $status = OrbitInput::post('status');
            $email = OrbitInput::post('email');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'bpp_user_id'      => $bppUserId,
                    'status'           => $status,
                    'email'            => $email,
                ),
                array(
                    'bpp_user_id'      => 'required|orbit.bpp_user.exists',
                    'email'            => 'orbit.bpp_user.email_exist_butme:'.$bppUserId,
                    'status'           => 'in:active,inactive',
                ),
                array(
                    'orbit.bpp_user.exists' => 'User not found',
                    'orbit.bpp_user.email_exist_butme' => 'The email has already been taken',
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            
            $updatedBPPUser = App::make('orbit.bpp_user.exists');
            
            OrbitInput::post('name', function($name) use ($updatedBPPUser) {
                $updatedBPPUser->name = $name;
            });

            OrbitInput::post('email', function($email) use ($updatedBPPUser) {
                $updatedBPPUser->email = $email;
            });

            OrbitInput::post('password', function($password) use ($updatedBPPUser) {
                $updatedBPPUser->password = Hash::make($password);
            });

            OrbitInput::post('merchant_id', function($merchantId) use ($updatedBPPUser) {
                $updatedBPPUser->merchant_id = $merchantId;
            });

            OrbitInput::post('status', function($status) use ($updatedBPPUser) {
                $updatedBPPUser->status = $status;
            });

            $updatedBPPUser->save();

            // Commit the changes
            $this->commit();

            $this->response->data = $updatedBPPUser;

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

    protected function registerCustomValidation()
    {
        // Check the existance of bpp_user id
        Validator::extend('orbit.bpp_user.exists', function ($attribute, $value, $parameters) {
            $BPPUser = BppUser::where('bpp_user_id', $value)->first();

            if (empty($BPPUser)) {
                return FALSE;
            }

            App::instance('orbit.bpp_user.exists', $BPPUser);

            return TRUE;
        });

        // Check the existance of email
        Validator::extend('orbit.bpp_user.email_exist_butme', function ($attribute, $value, $parameters) {
            $bppUserId = $parameters[0];
            $BPPUser = BppUser::where('email', $value)->where('bpp_user_id', '!=', $bppUserId)->first();

            if (empty($BPPUser)) {
                return TRUE;
            }

            return FALSE;
        });
        
    }

}
