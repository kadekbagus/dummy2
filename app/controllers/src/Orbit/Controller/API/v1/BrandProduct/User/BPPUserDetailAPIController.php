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
use App;
use stdclass;
use BppUser;
use Exception;

class BPPUserDetailAPIController extends ControllerAPI
{

    /**
     * User detail on brand product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function getDetail()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');

            $userId = $user->bpp_user_id;
            $userType = $user->user_type;
            $brandId = $user->base_merchant_id;

            $bppUserId = OrbitInput::get('bpp_user_id', null);

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'bpp_user_id'      => $bppUserId,
                ),
                array(
                    'bpp_user_id'      => 'required|orbit.bpp_user.exists',
                ),
                array(
                    'orbit.bpp_user.exists' => 'User not found'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            
            $userDetail = App::make('orbit.bpp_user.exists');

            $this->response->data = $userDetail;

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

    }

}
