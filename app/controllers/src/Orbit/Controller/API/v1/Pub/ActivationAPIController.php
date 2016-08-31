<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * API Controller for account activation.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use IntermediateBaseController;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\CommonAPIControllerTrait;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use OrbitShop\API\v1\ResponseProvider;
use Illuminate\Database\QueryException;
use Orbit\Helper\Net\GuestUserGenerator;
use Config;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Token;
use Validator;
use User;
use Lang;
use Mall;

class ActivationAPIController extends IntermediateBaseController
{
    protected $tokenObject = NULL;
    use CommonAPIControllerTrait;

    /**
     * POST - Activate Account
     *
     * @author Rio Astamal <rio@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `token`          (required) - Token to be check
     * @return Illuminate\Support\Facades\Response
     */
    public function postActivateAccount()
    {
        $this->response = new ResponseProvider();

        $activity = Activity::mobileci()
                            ->setActivityType('activation');
        try {
            $tokenValue = trim(OrbitInput::post('token'));
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');

            // Begin database transaction
            $this->beginTransaction();

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'token'         => $tokenValue,
                    'password'      => $password,
                    'password_confirmation' => $password2
                ),
                array(
                    'token'         => 'required|orbit_activation_empty_token',
                    'password'      => 'min:5|confirmed',
                ),
                array(
                    'orbit_activation_empty_token' => Lang::get('validation.orbit.empty.token')
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $token = $this->tokenObject;
            $user = User::with('userdetail')
                        ->excludeDeleted()
                        ->where('user_id', $token->user_id)
                        ->first();

            if (! is_object($token) || ! is_object($user)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            // update the token status so it cannot be use again
            $token->status = 'deleted';
            $token->save();

            // Update user password and activate them
            if (! empty($password)) {
                $user->user_password = Hash::make($password);
            }

            $user->status = 'active';
            $user->save();

            $this->response->message = Lang::get('statuses.orbit.activate.account');
            $this->response->data = $user;

            // Commit the changes
            $this->commit();

            // Log in the user
            $this->createLoginSession($user);

            // Get the registration record
            // $userSignUp = NULL;
            $location = NULL;

            if (! empty($user->userdetail->merchant_id)) {
                $location = Mall::find($user->userdetail->merchant_id);
            }

            // Successfull activation
            if (is_object($location)) {
                $activity->setLocation($location);
            }

            $activity->setUser($user)
                     ->setActivityName('activation_ok')
                     ->setActivityNameLong('Account Activation')
                     ->setModuleName('Application')
                     ->responseOK()
                     ->save();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($this->response);
    }

    /**
     * @return boolean
     * @throws Exception
     */
    protected function registerCustomValidation()
    {
        $me = $this;
        Validator::extend('orbit_activation_empty_token', function ($attribute, $value, $parameters) use ($me) {
            $token = Token::active()
                          ->registrationToken()
                          ->where('token_value', $value)
                          ->first();

            if (empty($token)) {
                return FALSE;
            }

            $me->tokenObject = $token;

            return TRUE;
        });
    }

    /**
     * @return void
     */
    protected function createLoginSession($user)
    {
        try{
            // update current session if exists
            $this->session->start(array(), 'no-session-creation');
            $sessionData = $this->session->read(NULL);
            $sessionData['logged_in'] = TRUE;
            $sessionData['user_id'] = $user->user_id;
            $sessionData['email'] = $user->user_email;
            $sessionData['role'] = $user->role->role_name;
            $sessionData['fullname'] = $user->getFullName();

            $this->session->update($sessionData);
        } catch (\Exception $e) {
            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->user_id,
                'email'     => $user->user_email,
                'role'      => $user->role->role_name,
                'fullname'  => $user->getFullName()
            );
            $this->session->enableForceNew()->start($data);

            $guestConfig = [
                'session' => $this->session
            ];
            $guest = GuestUserGenerator::create($guestConfig)->generate();
            $guestData = array();
            $guestData['guest_user_id'] = $guest->user_id;
            $guestData['guest_email'] = $guest->user_email;

            $this->session->update($guestData);
        }


        // Send the session id via HTTP header
        $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
        $sessionHeader = 'Set-' . $sessionHeader;
        $this->customHeaders[$sessionHeader] = $this->session->getSessionId();
    }
}