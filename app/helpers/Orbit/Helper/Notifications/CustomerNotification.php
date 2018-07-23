<?php namespace Orbit\Helper\Notifications;

use Orbit\Helper\Notifications\Notification;
use Orbit\Helper\Notifications\Contracts\EmailNotificationInterface;

/**
 * Base Customer Notification class.
 *
 * @author Budi <budi@dominopos.com>
 */
abstract class CustomerNotification extends Notification implements EmailNotificationInterface 
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

    protected function getRecipientEmail()
    {
        return $this->getCustomerEmail();
    }

    protected function getRecipientName()
    {
        return $this->getCustomerName();
    }

}
