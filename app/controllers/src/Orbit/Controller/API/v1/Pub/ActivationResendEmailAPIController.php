<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * API Controller for account activation.
 *
 * @author Ahmad <ahmad@dominopos.com>
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
use Queue;
use \Orbit\Helper\Exception\OrbitCustomException;

class ActivationResendEmailAPIController extends IntermediateBaseController
{
    /**
     * User $user
     */
    protected $user = NULL;

    use CommonAPIControllerTrait;

    /**
     * POST - Resend Activation Email
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `email`          (required) - Email
     * @return Illuminate\Support\Facades\Response
     */
    public function postResendActivationLink()
    {
        $this->response = new ResponseProvider();

        $activity = Activity::mobileci()
                            ->setActivityType('activation');
        try {
            $email = trim(OrbitInput::post('email'));
            $language = OrbitInput::get('language', 'id');
            $redirectToUrl = OrbitInput::get('to_url', NULL);

            // Begin database transaction
            $this->beginTransaction();

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'email'                 => $email,
                ),
                array(
                    'email'                 => 'required|email|orbit_empty_email',
                ),
                array(
                    'orbit_empty_email' => Lang::get('validation.orbit.empty.email'),
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // override error message if user is already active
            if ($this->user->status === 'active') {
                $errorMessage = 'User already active';
                throw new OrbitCustomException($errorMessage, User::USER_ALREADY_ACTIVE_ERROR_CODE, NULL);
            }

            // delete old token first
            $token = Token::active()
                    ->where('email', $email)
                    ->where('token_name', 'user_registration_mobile')
                    ->first();

            if (is_object($token)) {
                $token->delete(true);
            }

            $this->commit();

            // Send email process to the queue
            Queue::push('Orbit\\Queue\\RegistrationMail', [
                'user_id' => $this->user->user_id,
                'languageId' => $language,
                'mode' => 'gotomalls',
                'redirect_to_url' => $redirectToUrl
                ],
                Config::get('orbit.registration.mobile.queue_name', 'gtm_email')
            );

            $activity->setUser($this->user)
                     ->setActivityName('resend_activation_link')
                     ->setActivityNameLong('Resend Activation Link')
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
        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

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
        Validator::extend('orbit_empty_email', function ($attribute, $value, $parameters) use ($me) {
            $user = User::excludeDeleted()
                        ->whereHas('role', function($q) {
                            $q->where('role_name', 'Consumer');
                        })
                        ->where('user_email', $value)
                        ->first();

            if (empty($user)) {
                return FALSE;
            }

            $me->user = $user;

            return TRUE;
        });
    }
}