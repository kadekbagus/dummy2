<?php namespace Orbit\Helper\Notifications;

use Config;
use Log;
use Orbit\FakeJob;
use Orbit\Helper\Notifications\Exceptions\NotificationMethodsEmptyException;
use Queue;
use User;

/**
 * Base Notification class.
 * This helper inspired by Laravel 5's Notification feature.
 *
 * @todo  use a single sender class for each notification method.
 * @todo  separate queue name for each notification method.
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

    /**
     * Local/Indonesia Timezone mapping.
     *
     * @var [type]
     */
    protected $timezoneMapping = [
        'asia/jakarta' => 'WIB',
        'asia/makassar' => 'WITA',
        'asia/jayapura' => 'WIT',
        'utc' => 'UTC',
        '' => 'UTC',
    ];

    /**
     * Context of each notification.
     * @var string
     */
    protected $context = '';

    /**
     * Signature for each notification.
     * @var string
     */
    protected $signature = '';

    /**
     * List of disabled notifications.
     * @var array
     */
    protected $disabledNotifications = [];

    /**
     * List of blacklisted recipient/user emails.
     * @var array
     */
    protected $blacklistedRecipients = [
        'email' => [],
    ];

    /**
     * Log ID.
     * @var string
     */
    protected $logID = 'OrbitNotification';

    /**
     * Construct the notification.
     *
     * @param string|array|User $notifiable the target recipients.
     */
    function __construct($notifiable = null)
    {
        $this->setNotifiable($notifiable);
    }

    /**
     * Set the notifiable instance/object.
     *
     * @param string|array|User the recipient
     */
    public function setNotifiable($notifiable)
    {
        // If notifiable is a string (assume an email address),
        // then create a temporary User instance and set it
        // as the receipient.
        if (is_string($notifiable)) {
            $tempUser = new User;
            $tempUser->email = $notifiable;

            $notifiable = $tempUser;
        }

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
        // Check if email is being blacklisted.
        // If so, then do nothing.
        $this->blacklistedRecipients['email'] = $this->getBlacklistedEmails();
        $emailData = $this->getEmailData();
        $recipientEmail = isset($emailData['recipientEmail']) ? $emailData['recipientEmail'] : '';

        if (in_array($recipientEmail, $this->blacklistedRecipients['email'])) {
            return;
        }

        if ($this->shouldQueue) {
            // Override the delay if needed.
            $this->notificationDelay = $customDelay === 0 ? $this->notificationDelay : $customDelay;

            Queue::later(
                $this->notificationDelay,
                get_class($this) . '@toEmail',
                $emailData,
                $this->getQueueName()
            );
        }
        else {
            $fakeJob = new FakeJob();
            $this->toEmail($fakeJob, $emailData);
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
     *
     * @param  integer $customDelay [description]
     * @return [type]         [description]
     */
    public function send($customDelay = 0)
    {
        $notificationMethods = $this->notificationMethods();
        $this->loadDisabledNotifications();

        if (empty($notificationMethods)) {
            throw new NotificationMethodsEmptyException("Please set at least 1 notification method.");
        }

        foreach($notificationMethods as $method) {
            // Check if notification is disabled for current method.
            // If so, then continue checking for next method.
            if ($this->notificationIsDisabled($method)) {
                \Log::info("Notification [{$this->context}.{$this->signature}] is disabled.");
                continue;
            }

            if (is_array($this->notifiable)) {

                $tempNotifiables = $this->notifiable;
                foreach($tempNotifiables as $notifiable) {
                    $this->setNotifiable($notifiable);
                    $this->{$this->notificationMethodsActions[$method]}($customDelay);
                }
            }
            else {
                $this->{$this->notificationMethodsActions[$method]}($customDelay);
            }
        }
    }

    /**
     * Get local area/region time representation, e.g. WIB/WITA/WIT.
     *
     * @param  [type] $payment [description]
     * @return [type]          [description]
     */
    protected function getLocalTimezoneName($timezone = 'UTC')
    {
        $timezone = strtolower($timezone);
        return isset($this->timezoneMapping[$timezone])
                    ? $this->timezoneMapping[$timezone]
                    : '';
    }

    /**
     * Load list of disabled notifications from config.
     *
     * @return [type] [description]
     */
    protected function loadDisabledNotifications() {
        $this->disabledNotifications = Config::get('orbit.disabled_notifications', []);
    }

    /**
     * Determine if current notification is disabled
     * by checking its existance in the list of disabled notifications config.
     *
     * @return [type] [description]
     */
    protected function notificationIsDisabled($method = '')
    {
        return ! empty($this->signature)
               && ! empty($this->context)
               && isset($this->disabledNotifications[$this->context][$method])
               && in_array($this->signature, $this->disabledNotifications[$this->context][$method]);
    }

    /**
     * Get list of blacklisted emails.
     *
     * @return [type] [description]
     */
    protected function getBlacklistedEmails()
    {
        return Config::get('orbit.blacklisted_notification_recipients.email', []);
    }

    /**
     *
     * @param  string $message [description]
     * @param  string $logType [description]
     * @return [type]          [description]
     */
    protected function log($message = '', $logType = 'info')
    {
        Log::{$logType}(($this->logID ? "{$this->logID}: " : '') . $message);
    }
}
