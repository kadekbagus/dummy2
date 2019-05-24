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
use UserDetail;
use Lang;
use Mall;
use Hash;
use \Orbit\Helper\Exception\OrbitCustomException;
use Carbon\Carbon;
use Event;

class ActivationAPIController extends IntermediateBaseController
{
    protected $tokenObject = NULL;

    /**
     * Boolean flag to save activation activity without token
     */
    protected $saveAsAuto = FALSE;

    /**
     * User $user
     */
    protected $user = NULL;

    /**
     * string social media from ('facebook', 'google')
     */
    protected $socialFrom = NULL;

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
            if(! $this->saveAsAuto) {
                $activityNameLong = 'Account Activation';
                $tokenValue = trim(OrbitInput::post('token', null));

                // check the token first
                $token = Token::where('token_value', $tokenValue)
                        ->where('token_name', 'user_registration_mobile')
                        ->first();

                if (! is_object($token)) {
                    $errorMessage = Lang::get('validation.orbit.empty.token');
                    throw new OrbitCustomException($errorMessage, Token::TOKEN_NOT_FOUND_ERROR_CODE, NULL);
                }

                if ($token->expire <= Carbon::now()) {
                    $errorMessage = Lang::get('validation.orbit.empty.token_expired');
                    throw new OrbitCustomException($errorMessage, Token::TOKEN_EXPIRED_ERROR_CODE, NULL);
                }

                $user = User::excludeDeleted()
                            ->where('user_id', $token->user_id)
                            ->first();

                // override error message if user is already active
                if ($user->status === 'active') {
                    $errorMessage = 'Your link has expired';
                    throw new OrbitCustomException($errorMessage, User::USER_ALREADY_ACTIVE_ERROR_CODE, NULL);
                }

                // Begin database transaction
                $this->beginTransaction();

                $this->registerCustomValidation();

                $current_date = date('Y-m-d');
                $validator = Validator::make(
                    array(
                        'token'                 => $tokenValue,
                    ),
                    array(
                        'token'                 => 'required|orbit_activation_empty_token',
                    ),
                    array(
                        'orbit_activation_empty_token' => Lang::get('validation.orbit.empty.token'),
                    )
                );

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $token = $this->tokenObject;

                if (! is_object($token) || $token->status !== 'active' || ! is_object($user)) {
                    $message = Lang::get('validation.orbit.access.loginfailed');
                    ACL::throwAccessForbidden($message);
                }

                // update the token status so it cannot be use again
                $token->status = 'deleted';
                $token->save();

                $user->status = 'active';
                $user->save();
            } else {
                $from = $this->socialFrom;
                $activityNameLong = sprintf('Auto Account Activation from %s', ucfirst($from));
                $user = $this->user;
                if (! is_object($user)) {
                    OrbitShopAPI::throwInvalidArgument('User you specified is not valid');
                }
            }

            // append redirect_to_url metadata if any
            if (! empty($token->metadata)) {
                $metadata = json_decode($token->metadata);
                if (isset($metadata->redirect_to_url) && ! empty($metadata->redirect_to_url)) {
                    $user->redirect_to_url = $metadata->redirect_to_url;
                }
            }

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
                     ->setActivityNameLong($activityNameLong)
                     ->setModuleName('Application')
                     ->responseOK()
                     ->save();

            Event::fire('orbit.user.activation.success', $user);

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
        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollBack();
        } catch (Exception $e) {
            $this->response->code = 1;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($this->response);
    }

    /**
     * Set saveAsAuto value
     * @return ActivationAPIController
     */
    public function setSaveAsAutoActivation($user, $from)
    {
        $this->saveAsAuto = TRUE;
        $this->user = $user;
        $this->socialFrom = $from;
        return $this;
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
                          ->notExpire()
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
            $sessionData['status'] = $user->status;

            $this->session->update($sessionData);
        } catch (\Exception $e) {
            // Start the orbit session
            $data = array(
                'logged_in' => TRUE,
                'user_id'   => $user->user_id,
                'email'     => $user->user_email,
                'role'      => $user->role->role_name,
                'fullname'  => $user->getFullName(),
                'guest_user_id' => $guest->user_id,
                'guest_email' => $guest->user_email
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
