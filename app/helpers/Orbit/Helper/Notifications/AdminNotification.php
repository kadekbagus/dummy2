<?php namespace Orbit\Helper\Notifications;

use Config;

use Orbit\Helper\Notifications\Notification;
use Orbit\Helper\Notifications\Contracts\EmailNotificationInterface;

/**
 * Base Notification class for Admin.
 *
 * @author Budi <budi@dominopos.com>
 */
abstract class AdminNotification extends Notification implements EmailNotificationInterface
{
    /**
     * @return string
     */
    protected function getQueueName()
    {
        return Config::get('orbit.registration.mobile.queue_name');
    }

    protected function notificationMethods()
    {
        // Set to ONLY notify via email.
        return ['email'];
    }

    /**
     * Get admin email.
     * 
     * @return [type] [description]
     */
    public function getRecipientEmail()
    {
        return $this->notifiable->email;
    }

}
