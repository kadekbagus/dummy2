<?php namespace Orbit\Notifications\DigitalProduct;

use Exception;
use Log;
use Mail;
use Config;
use Orbit\Helper\Notifications\Contracts\EmailNotificationInterface;
use Orbit\Helper\Notifications\CustomerNotification;
use Orbit\Notifications\Traits\HasContactTrait;
use Orbit\Notifications\Traits\HasPaymentTrait;

/**
 * Notify Customer that we can not process the pulsa.
 *
 * @author Budi <budi@dominopos.com>
 */
class CustomerDigitalProductNotAvailableNotification extends CustomerNotification implements EmailNotificationInterface
{
    use HasPaymentTrait, HasContactTrait;

    /**
     * Indicate if we should push this job to queue.
     * @var boolean
     */
    protected $shouldQueue = true;

    /**
     * @var integer
     */
    protected $notificationDelay = 3;

    protected $context = 'transaction';

    protected $signature = 'product-not-available-notification';

    protected $logID = 'CustomerDigitalProductNotAvailableNotification';

    function __construct($payment = null)
    {
        $this->payment = $payment;
    }

    public function getRecipientEmail()
    {
        return $this->getCustomerEmail();
    }

    /**
     * Get the email templates that will be used.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.customer-digital-product-not-available',
        ];
    }

    /**
     * Get email subject.
     *
     * @return [type] [description]
     */
    protected function getEmailSubject()
    {
        return trans('email-coupon-not-available.subject_digital_product', ['productType' => $this->productType], '', 'id');
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        $this->getObjectType();
        $this->resolveProductType();

        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'customerEmail'     => $this->getCustomerEmail(),
            'customerName'      => $this->getCustomerName(),
            'customerPhone'     => $this->getCustomerPhone(),
            'transaction'       => $this->getTransactionData(),
            'cs'                => $this->getContactData(),
            'transactionDateTime' => $this->payment->getTransactionDate('d F Y, H:i ') . " {$this->getLocalTimezoneName($this->payment->timezone_name)}",
            'emailSubject'      => $this->getEmailSubject(),
            'template'          => $this->getEmailTemplates(),
            'productType'       => $this->productType,
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
            Mail::send($data['template'], $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $mail->subject($data['emailSubject']);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });
        } catch (Exception $e) {
            $this->log('Exception. File: ' . $e->getFile() . '(' . $e->getLine() . ') ' . $e->getMessage());
            $this->log('Email data: ' . serialize($data));
        }

        $job->delete();
    }
}
