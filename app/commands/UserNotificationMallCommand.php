<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Carbon\Carbon as Carbon;
use Orbit\Helper\Util\CdnUrlGenerator;
use Orbit\Helper\OneSignal\OneSignal;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;

class UserNotificationMallCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'user-notification:mall';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command for sending mall notification.';

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
		$headings = null;
		$contents = null;
		$newUrl = null;
		$imageUrl = null;
		$userIds = null;
		$notificationTokens = null;
		$mongoNotifId = null;
		$attachmentPath = null;
		$cdnUrl = null;
        $mongoConfig = Config::get('database.mongodb');
        $mongoClient = MongoClient::create($mongoConfig);
        $oneSignalConfig = Config::get('orbit.vendor_push_notification.onesignal');

        $mallObjectNotificationSearch['status'] = 'pending';
        $mallObjectNotifications = $mongoClient->setQueryString($mallObjectNotificationSearch)
				                               ->setEndPoint('mall-object-notifications')
				                               ->request('GET');

	    if (! empty($mallObjectNotifications->data->records))
	    {
            foreach ($mallObjectNotifications->data->records as $key => $mallObjectNotification)
            {
            	$mallId = $mallObjectNotification->mall_id;
            	$mallObjectNotificationId = $mallObjectNotification->_id;
	            $mall = Mall::excludeDeleted('merchants')
							->leftJoin('media', 'media.object_id', '=', 'merchants.merchant_id')
					        ->where('media.media_name_long', '=', 'mall_logo_orig')
							->where('merchant_id', '=', $mallId)
							->first();

	            if (is_object($mall))
	            {
					// Get the user id
	            	$userFollowSearch = ['object_id'   => $mallId, 'object_type' => 'mall'];
		            $userFollow = $mongoClient->setQueryString($userFollowSearch)
						                      ->setEndPoint('user-follows')
						                      ->request('GET');

					if (count($userFollow->data->records) > 0) {
	                    foreach ($userFollow->data->records as $key => $value) {
	                        $user_ids[] = $value->user_id;
	                    }
	                }
	                $userIds = array_unique($user_ids);

	                // Get notification tokens
	                $tokenSearch = ['user_ids' => $userIds, 'notification_provider' => 'onesignal'];
		            $tokenData = $mongoClient->setQueryString($tokenSearch)
		                                     ->setEndPoint('user-notification-tokens')
		                                     ->request('GET');

		            if ($tokenData->data->total_records > 0) {
		                foreach ($tokenData->data->records as $key => $value) {
		                    $notification_token[] = $value->notification_token;
		                }
		            }
		            $notificationTokens = array_unique($notification_token);

		            $attachmentPath = (!empty($mall->path)) ? $mall->path : '';
		            $cdnUrl = (!empty($mall->cdnUrl)) ? $mall->cdnUrl : '';
		            $cdnConfig = Config::get('orbit.cdn');
                    $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
                    $imageUrl = $imgUrl->getImageUrl($attachmentPath, $cdnUrl);
                    $launchUrl = LandingPageUrlGenerator::create('mall', $mall->merchant_id, $mall->name)->generateUrl();
                    $headings = $mall->name;
                    $contents = 'There are new happenings in '.$mall->name;

                    // add query string for activity recording
                    $newUrl =  $launchUrl . '?notif_id=' . $mongoNotifId;

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
	                $notificationUpdate['vendor_notification_id'] = $newNotif->id;

	                $mallObjectNotificationUpdate['_id'] = $mallObjectNotificationId;
	                $mallObjectNotificationUpdate['status'] = 'sent';

	                $responseMallUpdate = $mongoClient->setFormParam($mallObjectNotificationUpdate)
		                                               ->setEndPoint('mall-object-notifications')
		                                               ->request('PUT');

		            // send as inApps notification
	                // if (! empty($storeObjectNotification->notification->user_ids)) {
	                //     foreach ($storeObjectNotification->notification->user_ids as $userId) {
	                //         $bodyInApps = [
	                //             'user_id'       => $userId,
	                //             'token'         => null,
	                //             'notifications' => $storeObjectNotification->notification,
	                //             'send_status'   => 'sent',
	                //             'is_viewed'     => false,
	                //             'is_read'       => false,
	                //             'created_at'    => $dateTime
	                //         ];

	                //         $inApps = $mongoClient->setFormParam($bodyInApps)
	                //                     ->setEndPoint('user-notifications') // express endpoint
	                //                     ->request('POST');
	                //     }
	                // }
	            }
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
		return array(
			array('example', InputArgument::REQUIRED, 'An example argument.'),
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null),
		);
	}

}
