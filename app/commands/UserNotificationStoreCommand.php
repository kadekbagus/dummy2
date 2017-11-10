<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Carbon\Carbon as Carbon;
use Orbit\Helper\Util\CdnUrlGenerator;
use Orbit\Helper\OneSignal\OneSignal;

class UserNotificationStoreCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'user-notification:store';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        //Check date and status
        $timezone = 'Asia/Makassar'; // now with jakarta timezone
        $timestamp = date("Y-m-d H:i:s");
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
        $dateTime = $date->toDateTimeString();

        $mongoConfig = Config::get('database.mongodb');
        $mongoClient = MongoClient::create($mongoConfig);
        $oneSignalConfig = Config::get('orbit.vendor_push_notification.onesignal');

        // check existing notification
        // TODO :  add filter by date gte today datetime
        // $queryStringStoreObject['start_date'] = $dateTimeNow;
        $queryStringStoreObject['status'] = 'pendingx';
        // $queryStringStoreObject['id'] = 'ObjectId("5a05166065784b130dadb2f8")';

        $storeObjectNotifications = $mongoClient->setQueryString($queryStringStoreObject)
                                ->setEndPoint('store-object-notifications')
                                ->request('GET');

        if (! empty($storeObjectNotifications->data->records)) {
            foreach ($storeObjectNotifications->data->records as $key => $storeObjectNotification) {

                // send to onesignal
                if (! empty($storeObjectNotification->notification->notification_tokens)) {
                    $mongoNotifId = $storeObjectNotification->notification->_id;

                    $launchUrl = $storeObjectNotification->notification->launch_url;
                    $headings = $storeObjectNotification->notification->headings;
                    $contents = $storeObjectNotification->notification->contents;
                    $notificationTokens = $storeObjectNotification->notification->notification_tokens;

                    $cdnConfig = Config::get('orbit.cdn');
                    $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');

                    $localPath = (! empty($storeObjectNotification->notification->attachment_path)) ? $storeObjectNotification->notification->attachment_path : '';
                    $cdnPath = (! empty($storeObjectNotification->notification->cdn_url)) ? $storeObjectNotification->notification->cdn_url : '';
                    $imageUrl = $imgUrl->getImageUrl($localPath, $cdnPath);

                    // add query string for activity recording
                    $newUrl =  $launchUrl . '?notif_id=' . $mongoNotifId;

                    // english is mandatory in onesignal, set en value with default language content
                    if (empty($headings->en)) {
                        $headings->en = $headings->$defaultLangName;
                    }

                    if (empty($contents->en)) {
                        $contents->en = $contents->$defaultLangName;
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
                    $bodyUpdate['vendor_notification_id'] = $newNotif->id;
                }

                // send as inApps notification
                if (! empty($storeObjectNotification->notification->user_ids)) {
                    foreach ($storeObjectNotification->notification->user_ids as $userId) {
                        $bodyInApps = [
                            'user_id'       => $userId,
                            'token'         => null,
                            'notifications' => $storeObjectNotification->notification,
                            'send_status'   => 'sent',
                            'is_viewed'     => false,
                            'is_read'       => false,
                            'created_at'    => $dateTime
                        ];

                        $inApps = $mongoClient->setFormParam($bodyInApps)
                                    ->setEndPoint('user-notifications') // express endpoint
                                    ->request('POST');
                    }
                }

                // Update status in notification collection from pending to sent
                $bodyUpdate['sent_at'] = $dateTime;
                $bodyUpdate['_id'] = $mongoNotifId;
                $bodyUpdate['status'] = 'sent';

                $responseUpdate = $mongoClient->setFormParam($bodyUpdate)
                                            ->setEndPoint('notifications') // express endpoint
                                            ->request('PUT');

                // Update status in store-object-notifications collection from pending to sent
                $storeBodyUpdate['_id'] = $storeObjectNotification->_id;
                $storeBodyUpdate['notification'] = $responseUpdate->data;
                $storeBodyUpdate['status'] = 'sent';

                $responseStoreUpdate = $mongoClient->setFormParam($storeBodyUpdate)
                                            ->setEndPoint('store-object-notifications') // express endpoint
                                            ->request('PUT');
            }
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array();
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array();
    }

}
