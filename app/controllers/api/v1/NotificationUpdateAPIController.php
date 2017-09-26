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

class NotificationUpdateAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    /**
     * POST - post update notification
     * @author shelgi <shelgi@dominopos.com>
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
    public function postUpdateNotification()
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
            $launchUrl = OrbitInput::post('launch_url');
            $attachmentUrl = OrbitInput::post('attachment_url');
            $defaultLanguage = OrbitInput::post('default_language', 'en');
            $headings = OrbitInput::post('headings');
            $contents = OrbitInput::post('contents');
            $type = OrbitInput::post('type');
            $status = OrbitInput::post('status', 'draft');
            $notificationTokens = OrbitInput::post('notification_tokens');
            $userIds = OrbitInput::post('user_ids');
            $mongoConfig = Config::get('database.mongodb');

            $validator = Validator::make(
                array(
                    'notification_id'     => $notificationId,
                    'default_language'    => $defaultLanguage,
                    'headings'            => $headings,
                    'contents'            => $contents,
                    'type'                => $type,
                    'status'              => $status,
                    'notification_tokens' => $notificationTokens,
                ),
                array(
                    'notification_id'     => 'required',
                    'default_language'    => 'required',
                    'headings'            => 'required',
                    'contents'            => 'required',
                    'type'                => 'required',
                    'status'              => 'required',
                    'notification_tokens' => 'required|array',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $mongoClient = MongoClient::create($mongoConfig);
            $oldNotification = $mongoClient->setEndPoint("notifications/$notificationId")->request('GET');

            if (empty($oldNotification)) {
                $errorMessage = 'Notification ID is not found';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if ($oldNotification->data->status === 'send') {
                $errorMessage = 'Can not update notification that has been sent';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
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
                '_id'                 => $notificationId,
                'title'               => $headings->$defaultLanguage,
                'launch_url'          => $launchUrl,
                'attachment_url'      => $attachmentUrl,
                'default_language'    => $defaultLanguage,
                'headings'            => $headings,
                'contents'            => $contents,
                'type'                => $type,
                'status'              => $status,
                'vendor_type'         => Config::get('orbit.vendor_push_notification.default'),
                'notification_tokens' => $notificationTokens,
                'user_ids'            => $userIds,
            ];

            if ($status !== 'draft') {
                $oneSignalConfig = Config::get('orbit.vendor_push_notification.onesignal');

                $data = [
                    'headings'           => $headings,
                    'contents'           => $contents,
                    'url'                => $launchUrl,
                    'include_player_ids' => $notificationTokens,
                    'ios_attachments'    => $attachmentUrl,
                    'big_picture'        => $attachmentUrl,
                    'adm_big_picture'    => $attachmentUrl,
                    'chrome_big_picture' => $attachmentUrl,
                ];

                $oneSignal = new OneSignal($oneSignalConfig);
                $newNotif = $oneSignal->notifications->add($data);

                $body['sent_at'] = $dateTime;
                $body['vendor_notification_id'] = $newNotif->id;
            }

            $mongoClient = MongoClient::create($mongoConfig)->setFormParam($body);
            $response = $mongoClient->setEndPoint('notifications') // express endpoint
                                    ->request('PUT');

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = null;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.query.error', array($this, $e));

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
            Event::fire('orbit.mall.getsearchmallcountry.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.mall.getsearchmallcountry.before.render', array($this, &$output));

        return $output;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}