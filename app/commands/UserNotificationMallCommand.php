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
        $mongoConfig = Config::get('database.mongodb');
        $mongoClient = MongoClient::create($mongoConfig);
        $queueName = Config::get('queue.connections.gtm_notification.queue', 'gtm_notification');

        $timezone = 'Asia/Makassar'; // jakarta timezone
        $timestamp = date("Y-m-d H:i:s");
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
        $dateTime = $date->toDateTimeString();

        $mallObjectNotificationSearch['status'] = 'pending';
        $mallObjectNotifications = $mongoClient->setQueryString($mallObjectNotificationSearch)
                                               ->setEndPoint('mall-object-notifications')
                                               ->request('GET');

        if (! empty($mallObjectNotifications->data->records))
        {
            foreach ($mallObjectNotifications->data->records as $key => $mallObjectNotification)
            {

                $mallId = $mallObjectNotification->mall_id;

                // Queue for single record mongoDB result
                Queue::push('Orbit\\Queue\\Notification\\UserMallNotificationQueue', [
                    'mall_id' => $mallId
                ], $queueName);

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
