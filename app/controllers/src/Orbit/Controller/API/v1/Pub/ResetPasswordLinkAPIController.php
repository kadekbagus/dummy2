<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\PubControllerAPI;
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
use stdClass;
use Activity;
use Orbit\Helper\Util\PaginationNumber;
use Queue;
use App;

class ResetPasswordLinkAPIController extends PubControllerAPI
{
    /**
     * POST - Reset password link
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string email (required) - Email of the customer
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postResetPasswordLink()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()
                            ->setActivityType('reset_password');
        try {
            $this->beginTransaction();

            $email = trim(OrbitInput::post('email'));
            $language = trim(OrbitInput::get('language', 'id'));

            if (trim($email) === '') {
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = User::with('apikey', 'userdetail', 'role')
                ->excludeDeleted()
                ->where('user_email', $email)
                ->whereHas('role', function($query)
                {
                    $query->where('role_name','Consumer');
                })
                ->first();

            if (! is_object($user)) {
                App::setLocale($language);
                $errorMessage = \Lang::get('validation.orbit.empty.forgot_email', ['email_addr' => $email]);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // remove all existing reset tokens
            $existing_token = Token::active()
                ->NotExpire()
                ->where('token_name', 'reset_password')
                ->where('user_id', $user->user_id)
                ->orderBy('expire', 'desc')
                ->first();

            // Token expiration, fallback to 30 days
            $expireInDays = Config::get('orbit.reset_password.reset_expire', 1);
            if(! is_object($existing_token)) {
                // create the new token
                // Token Settings
                $token = new Token();
                $token->token_name = 'reset_password';
                $token->token_value = $token->generateToken($email);
                $token->status = 'active';
                $token->email = $email;
                $token->expire = date('Y-m-d H:i:s', strtotime('+' . $expireInDays . ' days'));
                $token->ip_address = $_SERVER['REMOTE_ADDR'];
                $token->user_id = $user->user_id;
                $token->save();
            } else {
                // use the existing one, and extend the expiration date
                $token = $existing_token;
                $token->expire = date('Y-m-d H:i:s', strtotime('+' . $expireInDays . ' days'));
                $token->save();
            }

            // Send email process to the queue
            Queue::push('Orbit\\Queue\\ResetPasswordMail', [
                    'tokenId'  => $token->token_id,
                    'languageId' => $language
                ],
                Config::get('orbit.reset_password.queue_name', 'gtm_email')
            );

            // Successfull send reset password email
            $activity->setUser($user)
                     ->setActivityName('reset_password_ok')
                     ->setActivityNameLong('Reset Password Link')
                     ->responseOK();

            $this->commit();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
            $activity->setUser('guest')
                     ->setActivityName('reset_password_failed')
                     ->setActivityNameLong('Reset Password Link')
                     ->setNotes($e->getMessage())
                     ->responseFailed();

        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
            // Rollback the changes
            $this->rollBack();
            $activity->setUser('guest')
                     ->setActivityName('reset_password_failed')
                     ->setActivityNameLong('Reset Password Link')
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        $output = $this->render($httpCode);
        // Save the activity
        $activity->setModuleName('Application')->save();

        return $output;
    }
}