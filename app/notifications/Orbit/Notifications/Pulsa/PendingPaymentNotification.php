<?php namespace Orbit\Notifications\Pulsa;

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

use Orbit\Helper\Notifications\Payment\PendingPaymentNotification as Base;

/**
 * Base Pending Payment Notification class.
 *
 * @author Budi <budi@dominopos.com>
 */
class PendingPaymentNotification extends Base
{
    function __construct($payment = null)
    {
        parent::__construct($payment);
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
            'html' => 'emails.pending-payment.pulsa',
        ];
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'pulsaPhoneNumber' => $this->payment->extra_data,
        ]);
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
            if ($payment->pending()) {
                $data['paymentInfo'] = json_decode(unserialize($payment->midtrans->payment_midtrans_info), true);

                Mail::send($this->getEmailTemplates(), $data, function($mail) use ($data) {
                    $emailConfig = Config::get('orbit.registration.mobile.sender');

                    $subject = trans('email-pending-payment.subject');

                    $mail->subject($subject);
                    $mail->from($emailConfig['email'], $emailConfig['name']);
                    $mail->to($data['recipientEmail']);
                });
            }

        } catch (Exception $e) {
            Log::debug('Notification: PendingPayment email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
        }

        $job->delete();
    }
}
