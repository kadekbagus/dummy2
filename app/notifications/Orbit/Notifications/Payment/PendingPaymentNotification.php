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
 * Base Pending Payment Notification class.
 *
 * @author Budi <budi@dominopos.com>
 */
class PendingPaymentNotification extends CustomerNotification implements EmailNotificationInterface
{
    use HasPaymentTrait, HasContactTrait;

    protected $shouldQueue = true;

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
            'html' => 'emails.payment.pending-payment',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-pending-payment.subject', [], '', 'id');
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        $this->objectType = $this->getObjectType();

        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'customerEmail'     => $this->getCustomerEmail(),
            'customerName'      => $this->getCustomerName(),
            'customerPhone'     => $this->getCustomerPhone(),
            'transaction'       => $this->getTransactionData(),
            'cs'                => $this->getContactData(),
            'paymentExpiration' => $this->getPaymentExpirationDate(),
            'myWalletUrl'       => $this->getMyPurchasesUrl() . '/coupons',
            'cancelUrl'         => $this->getCancelUrl(),
            'transactionDateTime' => $this->payment->getTransactionDate('d F Y, H:i ') . $this->getLocalTimezoneName($this->payment->timezone_name),
            'emailSubject'      => $this->getEmailSubject(),
            'template'          => $this->getEmailTemplates(),
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
            $payment = PaymentTransaction::with(['midtrans'])->findOrFail($data['transaction']['id']);

            // Only send email if pending.
            // if ($payment->pending()) {
                $data['paymentInfo'] = json_decode(unserialize($payment->midtrans->payment_midtrans_info), true);

                Mail::send($data['template'], $data, function($mail) use ($data) {
                    $emailConfig = Config::get('orbit.registration.mobile.sender');

                    $subject = $data['emailSubject'];

                    $mail->subject($subject);
                    $mail->from($emailConfig['email'], $emailConfig['name']);
                    $mail->to($data['recipientEmail']);
                });
            // }

        } catch (Exception $e) {
            Log::debug('Notification: PendingPayment email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
        }

        $job->delete();
    }
}
