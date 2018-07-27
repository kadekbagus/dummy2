<?php namespace Orbit\Helper\Notifications;

/**
 * Base Notification class.
 *
 * @author Budi <budi@dominopos.com>
 *
 * @todo  add support for delaying notification.
 */
abstract class Notification {

    protected $notifiable = null;

    protected $queueName = '';

    abstract public function send();

    public function setNotifiable($notifiable)
    {
        $this->notifiable = $notifiable;

        return $this;
    }

    protected function getEmailData()
    {
        return [];
    }

    protected function getWebNotificationData()
    {
        return [];
    }

    protected function getEmailAddress()
    {
        return $this->notifiable->email;
    }

    protected function getName()
    {
        return $this->notifiable->name;
    }

    /**
     * Notify to Email.
     * 
     * @return [type] [description]
     */
    public function toEmail($job, $data)
    {
        // Implemented in the child class...
    }

    /**
     * Notify to Web...
     * Applicable for notifiable type User.
     * 
     * @return [type] [description]
     */
    public function toWeb($job, $data)
    {
        // Implemented in the child class...
    }
}