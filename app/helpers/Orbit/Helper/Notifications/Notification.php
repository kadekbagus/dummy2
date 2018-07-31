<?php namespace Orbit\Helper\Notifications;

use Queue;
use Config;
use Orbit\FakeJob;

use Orbit\Helper\Notifications\Exceptions\NotificationMethodsEmptyException;

/**
 * Base Notification class.
 * This helper inspired by Laravel 5's Notification feature.
 *
 * @todo  use a single sender class for each notification method.
 * @todo  support bulk recipients.
 *
 * @author Budi <budi@dominopos.com>
 */
abstract class Notification {

    /**
     * The notifiable instance.
     * 
     * @var null
     */
    protected $notifiable = null;

    /**
     * Indicate if we should add notification job to Queue.
     * 
     * @var boolean
     */
    protected $shouldQueue = false;

    /**
     * The delay before we make the notification ready to be sent.
     * 
     * @var integer
     */
    protected $notificationDelay = 3;

    private $notificationMethodsActions = [
        'email' => 'sendEmail',
        'inApp' => 'sendInApp',
    ];

    function __construct($notifable = null)
    {
        $this->setNotifiable($notifiable);
    }

    /**
     * Set the notifiable instance/object.
     * 
     * @param [type] $notifiable [description]
     */
    public function setNotifiable($notifiable)
    {
        $this->notifiable = $notifiable;

        return $this;
    }

    /**
     * Get the notification methods for the notification.
     * 
     * @return [type] [description]
     */
    protected function notificationMethods() {
        return [];
    }

    /**
     * Get the queue name that will be used.
     *
     * @todo  should return array so each notification method can use its own queue name.
     * 
     * @return string queue name.
     */
    protected function getQueueName()
    {
        // empty string means use the whatever default queue name set in config.
        return '';
    }

    /**
     * Send to email.
     * 
     * @return [type] [description]
     */
    protected function sendEmail($customDelay = 0)
    {
        if ($this->shouldQueue) {
            
            // Override the delay if needed.
            $this->notificationDelay = $customDelay === 0 ? $this->notificationDelay : $customDelay;

            Queue::later(
                $this->notificationDelay,
                get_class($this) . '@toEmail',
                $this->getEmailData(),
                $this->getQueueName()
            );
        }
        else {
            $fakeJob = new FakeJob();
            $this->toEmail($fakeJob, $this->getEmailData());
        }
    }

    /**
     * Send to in-app notification.
     * 
     * @return [type] [description]
     */
    protected function sendInApp($customDelay = 0)
    {
        if ($this->shouldQueue) {

            // Override the delay if needed.
            $this->notificationDelay = $customDelay === 0 ? $this->notificationDelay : $customDelay;

            Queue::later(
                $this->notificationDelay,
                get_class($this) . '@toWeb',
                $this->getInAppData(),
                $this->getQueueName()
            );
        }
        else {
            $fakeJob = new FakeJob();
            $this->toWeb($fakeJob, $this->getInAppData());
        }
    }

    /**
     * Send the notification.
     * Will check for enabled methods.
     * Will use queue if it is set to true.
     * 
     * @param  integer $delay [description]
     * @return [type]         [description]
     */
    public function send($customDelay = 0)
    {
        $notificationMethods = $this->notificationMethods();

        if (empty($notificationMethods)) {
            throw new NotificationMethodsEmptyException("Please set at least 1 notification method.");
        }

        foreach($notificationMethods as $method) {
            $this->{$this->notificationMethodsActions[$method]}($customDelay);
        }
    }

}
