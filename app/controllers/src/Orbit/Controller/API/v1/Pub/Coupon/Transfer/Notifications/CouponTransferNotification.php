<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Notifications;

use Exception;
use Log;
use Mail;
use Config;
use Orbit\Helper\Notifications\Contracts\EmailNotificationInterface;
use Orbit\Helper\Notifications\CustomerNotification;
use Orbit\Notifications\Traits\HasContactTrait;

/**
 * Base coupon transfer notification.
 *
 * @author Budi <budi@dominopos.com>
 */
class CouponTransferNotification extends CustomerNotification implements EmailNotificationInterface
{
    use HasContactTrait;

    protected $issuedCoupon;

    /**
     * Indicate if we should push this job to queue.
     * @var boolean
     */
    protected $shouldQueue = true;

    /**
     * @var integer
     */
    protected $notificationDelay = 3;

    protected $context = 'coupon-transfer';

    protected $recipientName = '';

    function __construct($issuedCoupon = null, $recipientName = '')
    {
        $this->issuedCoupon = $issuedCoupon;
        $this->recipientName = $recipientName;
    }

    public function getRecipientEmail()
    {
        return $this->notifiable->email;
    }

    public function getEmailTemplates()
    {
        return [];
    }

    public function getEmailData()
    {
        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'cs'                => $this->getContactData(),
            'templates'         => $this->getEmailTemplates(),
        ];
    }

    /**
     * Notify via email.
     * This method act as custom Queue handler.
     *
     * @param  [type] $notifiable [description]
     * @return [type]             [description]
     */
    public function toEmail($job, $data)
    {
        try {
            Mail::send($data['templates'], $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = $data['emailSubject'];

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

        } catch (Exception $e) {
            Log::info($this->logID . ': email exception. File: ' . $e->getFile() . ', Lines:' . $e->getLine() . ', Message: ' . $e->getMessage());
            Log::info($this->logID . ': email data: ' . serialize($data));
        }

        $job->delete();
    }

}
