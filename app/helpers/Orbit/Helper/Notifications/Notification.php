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
    }

    protected function getEmailData()
    {
        
    }

    protected function getWebNotificationData()
    {
        
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
    public function toEmail()
    {
        // Implemented in the child class...
    }

    /**
     * Notify to Web...
     * Applicable for notifiable type User.
     * 
     * @return [type] [description]
     */
    public function toWeb()
    {
        // Implemented in the child class...
    }
}