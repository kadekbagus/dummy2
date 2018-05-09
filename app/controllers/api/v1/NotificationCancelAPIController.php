<?php
/**
 * An API controller for canceling scheduled notification
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\OneSignal\OneSignal;
use Orbit\Helper\Util\CdnUrlGenerator;

class NotificationCancelAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    /**
     * POST - post cancel notification
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     *
     * @param string        `notification_id`
     * @param string        `launch_url`
     * @param string        `attachment_url`
     * @param string        `default_language`
     * @param object        `headings`
     * @param object        `contents`
     * @param string        `type`
     * @param string        `status`
     * @param array         `notification_tokens`
     * @param array         `user_ids`
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function postCancelNotification()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $notificationId = OrbitInput::post('notification_id');

            $validator = Validator::make(
                array(
                    'notification_id'     => $notificationId
                ),
                array(
                    'notification_id'     => 'required'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);

            $notification = $mongoClient->setEndPoint("notifications/$notificationId")->request('GET');

            if (!isset($notification->data)) {
                OrbitShopAPI::throwInvalidArgument('notification id not found');
            }

            if (strtolower($notification->data->status)=='canceled') {
                OrbitShopAPI::throwInvalidArgument('notification already canceled');
            }

            if (strtolower($notification->data->status)=='draft') {
                OrbitShopAPI::throwInvalidArgument('cannot cancel draft notification');
            }

            if (!isset($notification->data->schedule_date)) {
                OrbitShopAPI::throwInvalidArgument('schedule date not found');
            }

            if (!isset($notification->data->vendor_notification_id)) {
                OrbitShopAPI::throwInvalidArgument('vendor notification id not found');
            }

            $vendor_notification_id = $notification->data->vendor_notification_id;

            // cancel scheduled notification
            $oneSignalConfig = Config::get('orbit.vendor_push_notification.onesignal');
            $oneSignal = new OneSignal($oneSignalConfig);
            $cancelNotif = $oneSignal->notifications->cancel($vendor_notification_id);

            $response = null;
            if (isset($cancelNotif->success) && $cancelNotif->success == 1) {
                $body = [
                    '_id'    => $notificationId,
                    'status' => 'canceled'
                ];

                // Update notification with new data.
                $update = $mongoClient->setFormParam($body)
                                        ->setEndPoint('notifications') // express endpoint
                                        ->request('PUT');

                $newResponse = $mongoClient->setEndPoint("notifications/$notificationId")->request('GET');
                $response = $newResponse->data;
            }

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = $response;
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

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {
            $message = $e->getMessage();
            if ($e->getCode() == 8701 && strpos($message, 'Incorrect player_id format in include_player_ids') !== false) {
                $message = 'Notification token is not valid';
            }

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getLine();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}