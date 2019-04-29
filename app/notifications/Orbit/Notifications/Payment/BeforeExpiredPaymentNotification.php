<?php namespace Orbit\Notifications\Payment;

use DB;
use Mail;
use Config;
use Log;
use Queue;
use Exception;
use Coupon;
use PromotionRetailer;
use PaymentTransaction;

use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;
use Orbit\Helper\Util\CdnUrlGenerator;
use Carbon\Carbon;

use Orbit\Helper\Notifications\CustomerNotification;
use Orbit\Helper\Notifications\Contracts\EmailNotificationInterface;

use Orbit\Notifications\Traits\HasPaymentTrait;
use Orbit\Notifications\Traits\HasContactTrait;

/**
 * Before Expired Payment Notification class.
 * It is a reminder/alarm, that will be fired up at midnight to remind Customer
 * to complete the payment before expired.
 *
 * @author Budi <budi@dominopos.com>
 */
class BeforeExpiredPaymentNotification extends CustomerNotification implements EmailNotificationInterface
{
    use HasPaymentTrait, HasContactTrait;

    protected $shouldQueue = false;

    function __construct($payment = null)
    {
        $this->payment = $payment;
    }

    public function getRecipientEmail()
    {
        return $this->getCustomerEmail();
    }

    /**
     * Get the email templates.
     * At the moment we can use same template for both Sepulsa and Hot Deals.
     * Can be overriden in each receipt class if needed.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.before-payment-expired',
        ];
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'customerEmail'     => $this->getCustomerEmail(),
            'customerName'      => $this->getCustomerName(),
            'customerPhone'     => $this->getCustomerPhone(),
            'transaction'       => $this->getTransactionData(),
            'cs'                => $this->getContactData(),
            'paymentExpiration' => $this->getPaymentExpirationDate(),
            'myWalletUrl'       => $this->getMyPurchasesUrl(),
            'cancelUrl'         => $this->getCancelUrl(),
            'paymentInfo'       => $this->getPaymentInfo(),
            'hideExpiration'    => true,
            'transactionDateTime' => $this->payment->getTransactionDate('d F Y, H:i ') . $this->getLocalTimezoneName($this->payment->timezone_name),
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

                $subject = trans('email-before-transaction-expired.subject');

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });
        } catch (Exception $e) {
            Log::debug('Notification: BeforeExpiredPaymentNotification email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());

            // Rethrow exception to the caller command/class.
            throw new Exception("Error");
        }

        $job->delete();
    }
}
