<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Carbon\Carbon as Carbon;


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
    protected $description = 'Command for sending user store notification.';

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
        $timezone = 'Asia/Jakarta'; // now with jakarta timezone
        $timestamp = date("Y-m-d H:i:s");
        $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
        $dateTime = $date->toDateTimeString();
        $dateTimeNow = $date->setTimezone($timezone)->toDateTimeString();

        $mongoConfig = Config::get('database.mongodb');
        $mongoClient = MongoClient::create($mongoConfig);

        $queueName = Config::get('queue.connections.gtm_notification.queue', 'gtm_notification');

        // check existing notification
        $queryStringStoreObject['start_date'] = $dateTimeNow;
        $queryStringStoreObject['status'] = 'pending';

        $storeObjectNotifications = $mongoClient->setQueryString($queryStringStoreObject)
                                                ->setEndPoint('store-object-notifications')
                                                ->request('GET');

        $totalRecords = $storeObjectNotifications->data->total_records;

        if ($totalRecords > 0) {
            // Update store object notification status to 'processing' to prevent pending
            // items to be pushed into queue again.
            $notificationIds = [];
            foreach($storeObjectNotifications->data->records as $storeObjectNotification) {
                $notificationIds[] = $storeObjectNotification->_id;
            }

            $params = [
                'status' => 'processing',
                'notification_ids' => json_encode($notificationIds),
            ];

            $mongoClient->setFormParam($params)
                        ->setEndPoint('store-object-notifications')
                        ->request('PUT');

            foreach ($storeObjectNotifications->data->records as $key => $storeObjectNotification) {

                $objectId = $storeObjectNotification->object_id;
                $objectType = $storeObjectNotification->object_type;
                $mongoId = $storeObjectNotification->_id;

                // Queue per single record
                Queue::push('Orbit\\Queue\\Notification\\UserStoreNotificationQueue', [
                    'object_id' => $objectId,
                    'object_type' => $objectType,
                    'mongo_id' => $mongoId,
                ], $queueName);
            }
        }

        $this->info('Cronjob User Notification For Store, Running at ' . $dateTimeNow . '; Total record : ' . $totalRecords . ' successfully');

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
