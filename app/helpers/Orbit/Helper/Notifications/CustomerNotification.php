<?php namespace Orbit\Helper\Notifications;

use Illuminate\Support\Facades\Config;
use Orbit\Helper\Notifications\Notification;

/**
 * Base Customer Notification class.
 *
 * @author Budi <budi@dominopos.com>
 */
abstract class CustomerNotification extends Notification
{
    /**
     * @return [type] [description]
     */
    protected function getQueueName()
    {
        return Config::get('orbit.registration.mobile.queue_name');
    }

    /**
     * @override
     * @return [type] [description]
     */
    protected function notificationMethods()
    {
        // Set default to notify via email
        return ['email'];
    }

}
