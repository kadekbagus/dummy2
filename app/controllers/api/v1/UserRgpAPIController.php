<?php
/**
 * An API controller for managing token.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Carbon\Carbon;

class UserRgpAPIController extends ControllerAPI
{
    public function validateSession()
    {
        try {
            $sessionId = OrbitInput::get('X-OMS-RGP');
            $email = OrbitInput::get('email');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'email' => $email,
                    'sessionId' => $sessionId,
                ),
                array(
                    'email' => 'required',
                    'sessionId' => 'required|orbit.check.session:'.$email,
                ),
                array(
                    'orbit.check.session' => Lang::get('session not found'),
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $this->response->data = 'session valid';

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render();
    }

    protected function registerCustomValidation()
    {
        // Check session data
        Validator::extend('orbit.check.session', function ($attribute, $value, $parameters) {
            $sessionId = $value;
            $userEmail = $parameters[0];
            $session = DB::table('sessions')->where('session_id', $sessionId)->first();

            if (! $session) {
                return FALSE;
            }

            // check email
            $sessionData = unserialize($session->session_data);
            $sessionEmail = isset($sessionData->value['email']) ? $sessionData->value['email'] : '';

            if (($sessionEmail !== $userEmail) || $sessionEmail == '') {
                return FALSE;
            }

            // check expired
            $sessionExpired = isset($sessionData->expireAt) ? $sessionData->expireAt : 0;
            $currentDate = date(time());

            if ($currentDate > $sessionExpired) {
                return FALSE;
            }

            return TRUE;
        });
    }
}