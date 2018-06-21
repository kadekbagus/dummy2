<?php namespace Orbit\Notifications\Coupon\HotDeals;

use Orbit\Helper\Notifications\Notification;
use Orbit\Helper\Util\JobBurier;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Carbon\Carbon;

use Mail;
use Config;
use Log;
use Queue;
use Exception;

/**
 * Notify Customer that the voucher we try to take is not available.
 *
 * @author Budi <budi@dominopos.com>
 */
class CouponNotAvailableNotification extends Notification
{
    protected $payment = null;

    function __construct($payment = null)
    {
        $this->payment              = $payment;
        $this->queueName            = Config::get('orbit.registration.mobile.queue_name');
    }

    public function getEmailAddress()
    {
        return $this->payment->user_email;
    }

    public function getName()
    {
        return $this->payment->user_name;
    }

    public function getContactInfo()
    {
        return Config::get('orbit.contact_information.customer_service');
    }

    /**
     * Get the email data.
     * 
     * @return [type] [description]
     */
    protected function getEmailData()
    {
        return [
            'recipientEmail'    => $this->getEmailAddress(),
            'customerName'      => $this->getName(),
            'couponName'        => $this->payment->object_name,
            'maxRefundDate'     => Carbon::now('Asia/Jakarta')->addDay()->format('j M Y') . ' 16:00 WIB (GTM +7)',
            'paymentId'         => $this->payment->payment_transaction_id,
            'contact'           => $this->getContactInfo(),
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

            $emailTemplate = 'emails.coupon.hot-deals-customer-voucher-not-available';

            Mail::send($emailTemplate, $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = 'Voucher not Available';

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

        } catch (Exception $e) {
            Log::info('VoucherNotAvailable: email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
            Log::info('VoucherNotAvailable: email data: ' . serialize($data));
        }

        $job->delete();

        // Bury the job for later inspection
        // JobBurier::create($job, function($theJob) {
        //     // The queue driver does not support bury.
        //     $theJob->delete();
        // })->bury();
    }

    /**
     * Send notification.
     * 
     * @return [type] [description]
     */
    public function send($delay = 1)
    {
        Queue::later(
            $delay,
            "Orbit\Notifications\Coupon\HotDeals\CouponNotAvailableNotification@toEmail", 
            $this->getEmailData(),
            $this->queueName
        );

        // Other notification method can be added here...
    }
}
