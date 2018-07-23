<?php namespace Orbit\Helper\Notifications;

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
        // Set to notify via email by default.
        return ['email'];
    }

    /**
     * Get admin email.
     * 
     * @return [type] [description]
     */
    protected function getRecipientEmail()
    {
        return $this->notifiable->email;
    }

    /**
     * Get admin name.
     * 
     * @return [type] [description]
     */
    protected function getRecipientName()
    {
        return $this->notifiable->name;
    }

    /**
     * Get admin email (old method, for compatibility purpose)
     * 
     * @return [type] [description]
     */
    protected function getEmailAddress()
    {
        return $this->notifiable->email;
    }

    /**
     * Get admin name (old method, for compatibility purpose)
     * 
     * @return [type] [description]
     */
    protected function getName()
    {
        return $this->notifiable->name;
    }

}
