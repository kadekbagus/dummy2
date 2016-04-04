<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use User;
use Token;
use Mail;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;

class ResetPasswordLinkAPIController extends ControllerAPI
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
        try {
            $this->beginTransaction();

            $email = trim(OrbitInput::post('email'));

            if (trim($email) === '') {
                $errorMessage = \Lang::get('validation.required', array('attribute' => 'email'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $user = User::with('apikey', 'userdetail', 'role')
                ->excludeDeleted()
                ->where('user_email', $email)
                ->where('status', 'active')
                ->whereHas('role', function($query)
                {
                    $query->where('role_name','Consumer');
                })
                ->first();

            if (!is_object($user)) {
                $errorMessage = \Lang::get('validation.orbit.empty.email');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // remove all existing reset tokens
            $existing_tokens = Token::active()
                ->NotExpire()
                ->where('token_name', 'reset_password')
                ->where('user_id', $user->user_id)
                ->get();

            foreach ($existing_tokens as $existing_token) {
                $existing_token->delete();
            }

            // Token expiration, fallback to 30 days
            $expireInDays = Config::get('orbit.reset_password.reset_expire', 7);

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

            // URL Activation link
            $baseUrl = Config::get('orbit.reset_password.reset_base_url');
            $tokenUrl = sprintf($baseUrl, $token->token_value);
            $contactInfo = Config::get('orbit.contact_information.customer_service');

            $data = array(
                'token'             => $token->token_value,
                'email'             => $email,
                'first_name'        => $user->user_firstname,
                'token_url'         => $tokenUrl,
                'cs_phone'          => $contactInfo['phone'],
                'cs_email'          => $contactInfo['email'],
                'cs_office_hour'    => $contactInfo['office_hour']
            );
            
            $mailviews = array(
                'html' => 'emails.reset-password.customer-html',
                'text' => 'emails.reset-password.customer-text'
            );

            Mail::queue($mailviews, $data, function($message)
            {
                $emailconf = Config::get('orbit.reset_password.sender');
                $from = $emailconf['email'];
                $name = $emailconf['name'];

                $email = OrbitInput::post('email');
                $message->from($from, $name)->subject('Password Reset Request');
                $message->to($email);
            });

            $this->commit();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();

        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
            // Rollback the changes
            $this->rollBack();
        }

        $output = $this->render($httpCode);

        return $output;
    }
}