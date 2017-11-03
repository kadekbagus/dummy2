<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\Helper\MongoDB\Client as MongoClient;

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

    /* Example data
        [0] => stdClass Object
            (
                [_id] => 59f68e6fbf194d71d6de47c4
                [notification] => test
                [object_id] => LLGnAtXoxSkF16uz
                [object_type] => news
                [user_ids] => KmA85_LOVMKUxfuq
                [tokens] => 6666
                [status] => pending
                [start_date] => 2017-07-11 13:42:00
                [created_at] => 2017-07-11 13:42:00
            )

        [1] => stdClass Object
            (
                [_id] => 59f6951dbf194d71d6de47c5
                [notification] => [{
                [object_id] => LLGnAtXoxSkF16uz
                [object_type] => news
                [user_ids] => KmA85_LOVMKUxfuq
                [tokens] => Array
                    (
                        [0] => '32132135'
                        [1] => 6666
                    )

                [status] => pending
                [start_date] => 2017-07-11 13:42:00
                [created_at] => 2017-07-11 13:42:00
            )

    */

        // Initialisaztion parameter

        // $queryString = [
        //     'take' => $take,
        //     'skip' => $skip,
        // ];

        // $queryString['user_id'] = $user->user_id;
        // $queryString['object_type'] = $objectType;
        $mongoConfig = Config::get('database.mongodb');


        $mongoClient = MongoClient::create($mongoConfig);
        $endPoint = "store-object-notifications";
        // $endPoint = "mall-object-notifications";

        $userIds = $mongoClient
                                // ->setQueryString($queryString)
                                ->setEndPoint($endPoint)
                                ->request('GET');




        // Collect data from mongo
        $tokens = array();
        $userIds = array();

        // Sen to push notification and inapps notification
        $oneSignalConfig = Config::get('orbit.vendor_push_notification.onesignal');

        $mongoNotifId = $response->data->_id;
        $imageUrl = $attachmentUrl;
        $notif = $mongoClient->setEndPoint("notifications/$mongoNotifId")->request('GET');

        if ($files) {
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
            $imageUrl = $imgUrl->getImageUrl($notif->data->attachment_path, $notif->data->cdn_url);
        }

        // Send to onesignal
        if (! empty($tokens)) {
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
            $bodyUpdate['vendor_notification_id'] = $newNotif->id;
        }

        // Send as inApps notification
        if (! empty($userIds)) {
            foreach ($userIds as $userId) {
                $bodyInApps = [
                    'user_id'       => $userId,
                    'token'         => null,
                    'notifications' => $notif->data,
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

        $bodyUpdate['sent_at'] = $dateTime;
        $bodyUpdate['_id'] = $mongoNotifId;

        $responseUpdate = $mongoClient->setFormParam($bodyUpdate)
                                    ->setEndPoint('notifications') // express endpoint
                                    ->request('PUT');
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
