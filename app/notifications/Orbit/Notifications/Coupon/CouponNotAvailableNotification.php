<?php namespace Orbit\Notifications\Coupon;

use Orbit\Helper\Notifications\Notification;
use Orbit\Helper\Util\JobBurier;
use Carbon\Carbon;

use Mail;
use Config;
use Log;
use Queue;
use Exception;

/**
 * Notify Admin that the coupon we try to issue is not available.
 *
 * @author Budi <budi@dominopos.com>
 */
class CouponNotAvailableNotification extends Notification
{
    protected $payment = null;

    protected $reason = null;

    function __construct($payment = null, $reason = 'Unknown error.')
    {
        $this->payment              = $payment;
        $this->reason               = $reason;
        $this->queueName            = Config::get('orbit.registration.mobile.queue_name');
    }

    protected function getEmailAddress()
    {
        return $this->getCustomerEmail();
    }

    private function getCustomerEmail()
    {
        return $this->payment->user_email;
    }

    private function getCustomerName()
    {
        return $this->payment->user_name;
    }

    /**
     * Get the email data.
     * 
     * @return [type] [description]
     */
    protected function getEmailData()
    {
        $transaction          = [];
        $transaction['id']    = $this->payment->payment_transaction_id;
        $transaction['date']  = $this->payment->getTransactionDate('j M Y');
        $transaction['items'] = [];
        $transaction['total'] = $this->payment->getAmount();

        foreach ($this->payment->details as $item) {
            $transaction['items'][] = [
                'name'      => $item->object_name,
                'quantity'  => $item->quantity,
                'price'     => $item->getPrice(),
                'total'     => $item->getTotal(),
            ];
        }

        return [
            'recipientEmail'    => $this->getEmailAddress(),
            'customerEmail'     => $this->getCustomerEmail(),
            'customerName'      => $this->getCustomerName(),
            'customerPhone'     => $this->payment->phone,
            'transaction'       => $transaction,
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

            $emailTemplate = 'emails.coupon.admin-coupon-not-available';

            Mail::send($emailTemplate, $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = '[Admin] Can not Issue Coupon';

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

        } catch (Exception $e) {
            Log::info('CouponNotAvailable: email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
            Log::info('CouponNotAvailable: email data: ' . serialize($data));
        }

        $job->delete();

    }

    /**
     * Send notification.
     * 
     * @return [type] [description]
     */
    public function send($delay = 3)
    {
        Queue::later(
            $delay,
            "Orbit\Notifications\Coupon\CouponNotAvailableNotification@toEmail", 
            $this->getEmailData(),
            $this->queueName
        );

        // Other notification method can be added here...
    }

}
