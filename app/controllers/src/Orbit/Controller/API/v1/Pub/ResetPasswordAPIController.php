<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use User;
use Token;
use Mail;
use App;
use Lang;
use Hash;
use Validator;
use stdClass;
use Activity;
use Orbit\Helper\Util\PaginationNumber;

class ResetPasswordAPIController extends ControllerAPI
{
    /**
     * POST - Reset password
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `token`                     (required) - Valid password reset token value for customer
     * @param string    `password`                  (required) - Password
     * @param string    `password_confirmation`     (required) - Password confirmation
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postResetPassword()
    {
        $this->response = new ResponseProvider();
        $activity = Activity::portal()
                            ->setActivityType('reset_password');
        try {
            $this->registerCustomValidation();

            $tokenValue = trim(OrbitInput::post('token'));
            $password = OrbitInput::post('password');
            $password2 = OrbitInput::post('password_confirmation');

            $validator = Validator::make(
                array(
                    'token_value'   => $tokenValue,
                    'password'      => $password,
                    'password_confirmation' => $password2
                ),
                array(
                    'token_value'   => 'required|orbit.empty.reset_password.token',
                    'password'      => 'required|min:6|confirmed',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $token = App::make('orbit.empty.reset_password.token');
            $user = User::with('userdetail')
                ->excludeDeleted()
                ->where('user_id', $token->user_id)
                ->whereHas('role', function($query)
                {
                    $query->where('role_name','Consumer');
                })
                ->first();

            if (! is_object($token) || ! is_object($user)) {
                $message = Lang::get('validation.orbit.access.loginfailed');
                ACL::throwAccessForbidden($message);
            }

            // Begin database transaction
            $this->beginTransaction();

            // update the token status so it cannot be use again
            $token->status = 'deleted';
            $token->save();

            // Update user password and activate them
            $user->user_password = Hash::make($password);
            $user->status = 'active';
            $user->save();

            $this->response->message = Lang::get('statuses.orbit.updated.your_password');
            $this->response->data = $user;

            // Successfull reset password
            $activity->setUser($user)
                     ->setActivityName('reset_password_ok')
                     ->setActivityNameLong('Reset Password')
                     ->responseOK();

            // Commit the changes
            $this->commit();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
            $activity->setUser('guest')
                     ->setActivityName('reset_password_failed')
                     ->setActivityNameLong('Reset Password')
                     ->setNotes($e->getMessage())
                     ->responseFailed();

        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
            $activity->setUser('guest')
                     ->setActivityName('reset_password_failed')
                     ->setActivityNameLong('Reset Password')
                     ->setNotes($e->getMessage())
                     ->responseFailed();

        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
            $activity->setUser('guest')
                     ->setActivityName('reset_password_failed')
                     ->setActivityNameLong('Reset Password')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }
        // Save the activity
        $activity->setModuleName('Application')->save();

        return $this->render();
    }

    /**
     * GET - Check reset password token
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string token (required) - Token
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCheckResetPasswordToken()
    {
        $this->response = new ResponseProvider();
        try {
            $this->registerCustomValidation();
            $tokenValue = trim(OrbitInput::get('token'));
            $email = trim(OrbitInput::get('email'));

            $validator = Validator::make(
                array(
                    'token_value'   => $tokenValue,
                    'email'   => $email,
                ),
                array(
                    'token_value'   => 'required|orbit.empty.reset_password.token',
                    'email'         => 'required|orbit.empty.reset_password.email:' . $tokenValue,
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $this->response->message = 'Token OK';
            $this->response->data = $tokenValue;

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render();
    }

    protected function registerCustomValidation()
    {
        // Check the existance of token
        Validator::extend('orbit.empty.reset_password.token', function ($attribute, $value, $parameters) {
            $token = Token::active()
                ->NotExpire()
                ->where('token_value', $value)
                ->where('token_name', 'reset_password')
                ->first();

            if (empty($token)) {
                return FALSE;
            }

            App::instance('orbit.empty.reset_password.token', $token);

            return TRUE;
        });

        // Check the existance of token
        Validator::extend('orbit.empty.reset_password.email', function ($attribute, $value, $parameters) {
            $token = Token::active()
                ->NotExpire()
                ->where('token_value', $parameters[0])
                ->where('token_name', 'reset_password')
                ->first();

            $user = User::where('user_id', $token->user_id)
                ->where('user_email', $value)
                ->first();

            if (empty($user)) {
                return FALSE;
            }

            App::instance('orbit.empty.reset_password.email', $token);

            return TRUE;
        });
    }
}
