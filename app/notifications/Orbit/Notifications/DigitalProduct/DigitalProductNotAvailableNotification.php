<?php namespace Orbit\Notifications\DigitalProduct;

use Orbit\Helper\Notifications\AdminNotification;

use Orbit\Notifications\Traits\HasPaymentTrait as HasPayment;

use Mail;
use Config;
use Log;
use Exception;

/**
 * Notify Admin that the digital product we try to issue is not available.
 *
 * @author Budi <budi@dominopos.com>
 */
class DigitalProductNotAvailableNotification extends AdminNotification
{
    use HasPayment;

    protected $reason = null;

    /**
     * Indicate if we should push this job to queue.
     * @var boolean
     */
    protected $shouldQueue = true;

    /**
     * @var integer
     */
    protected $notificationDelay = 3;

    protected $logID = 'DigitalProductNotification';

    function __construct($payment = null, $reason = 'Internal Server Error.')
    {
        $this->payment              = $payment;
        $this->reason               = $reason;
        $this->queueName            = Config::get('orbit.registration.mobile.queue_name');
    }

    /**
     * Get the email templates.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.admin-digital-product-not-available',
        ];
    }

    /**
     * Get the email subject.
     * @return [type] [description]
     */
    public function getEmailSubject()
    {
        $productType = trans("email-payment.product_type.{$this->productType}", [], '', 'id');
        return trans('email-coupon-not-available-admin.subject_digital_product', ['productType' => $productType], '', 'en');
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        $this->resolveProductType();

        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'customerEmail'     => $this->getCustomerEmail(),
            'customerName'      => $this->getCustomerName(),
            'customerPhone'     => $this->getCustomerPhone(),
            'transaction'       => $this->getTransactionData(),
            'transactionDateTime' => $this->payment->getTransactionDate('d F Y, H:i ') . " {$this->getLocalTimezoneName($this->payment->timezone_name)}",
            'reason'            => stripos($this->reason, 'out of stock') >= 0 ? $this->reason : '',
            'emailSubject'      => $this->getEmailSubject(),
            'providerName'      => $this->getProviderName(),
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
            Mail::send($this->getEmailTemplates(), $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');
                $this->log(serialize($emailConfig));

                $mail->subject($data['emailSubject']);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });
        } catch (Exception $e) {
            $this->log('Exception on Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
            $this->log('Exception data: ' . serialize($data));
        }

        $job->delete();
    }

}
