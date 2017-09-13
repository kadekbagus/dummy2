<?php namespace Orbit\Controller\API\v1\Pub\UserNotification;

/**
 * An API controller for getting generic activity.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Config;
use stdClass;
use DB;
use Validator;
use UserNotification;

class UserNotificationNewAPIController extends PubControllerAPI
{
    /**
     * post - create user notificatin
     *
     * @author shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string notification_token
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUserNotification()
    {
        $httpCode = 200;

        try {
            $user = $this->getUser();

            $notificationToken = OrbitInput::post('notification_token');

            $validator = Validator::make(
                array(
                    'notification_token' => $notificationToken
                ),
                array(
                    'notification_token' => 'required'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $provider = Config::get('orbit.vendor_push_notification.default', 'onesignal');

            DB::beginTransaction();

            $userNotification = new UserNotification();
            $userNotification->user_id = $user->user_id;
            $userNotification->user_role_id = $user->user_role_id;
            $userNotification->notification_token = $notificationToken;
            $userNotification->notification_provider = $provider;
            $userNotification->save();

            DB::commit();

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
            $this->response->data = null;

        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

        } catch (QueryException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

        } catch (Exception $e) {

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }
}