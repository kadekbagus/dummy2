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
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;

class UserNotificationDeleteAPIController extends PubControllerAPI
{
    /**
     * post - delete user notification
     *
     * @author shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string notification_token
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteUserNotification()
    {
        $httpCode = 200;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);
            $notificationId = OrbitInput::post('notification_id', null);

            if (! empty($notificationId)) {
                // delete by notification id
                $deleteNotif = $mongoClient->setEndPoint("user-notifications/$notificationId")->request('DELETE');
            } else {
                // delete notification by user_id
                $bodyDelete = [
                    'user_id' => $user->user_id
                ];

                $deleteNotif = $mongoClient->setFormParam($bodyDelete)
                                           ->setEndPoint("user-notifications")
                                           ->request('DELETE');
            }

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