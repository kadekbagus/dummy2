<?php
/**
 * An API controller for mall location (country,city,etc).
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

class NotificationNewAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    /**
     * POST - post new notification
     * @author shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
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
    public function postNewNotification()
    {
        $mongoConfig = Config::get('database.mongodb');
        $mongoClient = MongoClient::create($mongoConfig);
        $mongoNotifId = '';

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

            $launchUrl = OrbitInput::post('launch_url');
            $attachmentUrl = OrbitInput::post('attachment_url');
            $defaultLanguage = OrbitInput::post('default_language', 'en');
            $headings = OrbitInput::post('headings');
            $contents = OrbitInput::post('contents');
            $type = OrbitInput::post('type');
            $status = OrbitInput::post('status', 'draft');
            $notificationTokens = OrbitInput::post('notification_tokens');
            $userIds = OrbitInput::post('user_ids');
            $targetAudience = OrbitInput::post('target_audience');
            $files = OrbitInput::files('images');

            $validator = Validator::make(
                array(
                    'launch_url'          => $launchUrl,
                    'default_language'    => $defaultLanguage,
                    'headings'            => $headings,
                    'contents'            => $contents,
                    'type'                => $type,
                    'status'              => $status,
                ),
                array(
                    'launch_url'          => 'required',
                    'default_language'    => 'required',
                    'headings'            => 'required',
                    'contents'            => 'required',
                    'type'                => 'required',
                    'status'              => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (empty($notificationTokens) && empty($userIds)) {
                OrbitShopAPI::throwInvalidArgument('Notification tokens and user id is empty');
            }

            if (! empty($notificationTokens)) {
                if (count($notificationTokens) !== count(array_unique($notificationTokens))) {
                    OrbitShopAPI::throwInvalidArgument('Duplicate token in Notification Tokens');
                }
                $notificationTokens = array_unique($notificationTokens);
            }

            if (! empty($userIds)) {
                if (count($userIds) !== count(array_unique($userIds))) {
                    OrbitShopAPI::throwInvalidArgument('Duplicate user ids');
                }
                $userIds = array_unique($userIds);
            }

            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->toDateTimeString();

            $headings = @json_decode($headings);
            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument('Heading JSON not valid');
            }

            $contents = @json_decode($contents);
            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument('Contents JSON not valid');
            }

            if (empty($headings->$defaultLanguage)) {
                OrbitShopAPI::throwInvalidArgument('Heading in default language is empty');
            }

            if (empty($contents->$defaultLanguage)) {
                OrbitShopAPI::throwInvalidArgument('Content in default language is empty');
            }

            $body = [
                'title'               => $headings->$defaultLanguage,
                'launch_url'          => $launchUrl,
                'attachment_url'      => $attachmentUrl,
                'default_language'    => $defaultLanguage,
                'headings'            => $headings,
                'contents'            => $contents,
                'type'                => $type,
                'status'              => $status,
                'created_at'          => $dateTime,
                'vendor_type'         => Config::get('orbit.vendor_push_notification.default'),
                'notification_tokens' => $notificationTokens,
                'user_ids'            => $userIds,
                'target_audience_ids' => $targetAudience,
            ];

            $response = $mongoClient->setFormParam($body)
                                    ->setEndPoint('notifications') // express endpoint
                                    ->request('POST');

            Event::fire('orbit.notification.postnotification.after.save', array($this, $response->data->_id));

            if ($status !== 'draft') { // send
                $oneSignalConfig = Config::get('orbit.vendor_push_notification.onesignal');

                $mongoNotifId = $response->data->_id;
                $imageUrl = $attachmentUrl;
                if ($files) {
                    $notif = $mongoClient->setEndPoint("notifications/$mongoNotifId")->request('GET');

                    $cdnConfig = Config::get('orbit.cdn');
                    $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
                    $imageUrl = $imgUrl->getImageUrl($notif->data->attachment_path, $notif->data->cdn_url);
                }

                // add query string for activity recording
                $newUrl =  $launchUrl . '?notif_id=' . $mongoNotifId;
                if (parse_url($launchUrl, PHP_URL_QUERY)) { // if launch url containts query string
                    $newUrl =  $launchUrl . '&notif_id=' . $mongoNotifId;
                }

                $data = [
                    'headings'           => $headings,
                    'contents'           => $contents,
                    'url'                => $newUrl,
                    'include_player_ids' => $notificationTokens,
                    'ios_attachments'    => $imageUrl,
                    'big_picture'        => $imageUrl,
                    'adm_big_picture'    => $imageUrl,
                    'chrome_big_picture' => $imageUrl,
                    'chrome_web_image'   => $imageUrl,
                ];

                $oneSignal = new OneSignal($oneSignalConfig);
                $newNotif = $oneSignal->notifications->add($data);

                $body['sent_at'] = $dateTime;
                $body['vendor_notification_id'] = $newNotif->id;
                $body['_id'] = $mongoNotifId;

                $response = $mongoClient->setFormParam($body)
                                    ->setEndPoint('notifications') // express endpoint
                                    ->request('PUT');
            }

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = $response->data;
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
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
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

            // rollback
            if (! empty($mongoNotifId)) {
                $deleteNotif = $mongoClient->setEndPoint("notifications/$mongoNotifId")->request('DELETE');
            }

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $message;
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