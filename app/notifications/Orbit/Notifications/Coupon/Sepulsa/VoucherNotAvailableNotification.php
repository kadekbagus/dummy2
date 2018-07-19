<?php namespace Orbit\Notifications\Coupon\Sepulsa;

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
 * (Maybe because Sepulsa server error or the voucher is not available/expired).
 *
 * @author Budi <budi@dominopos.com>
 */
class VoucherNotAvailableNotification extends Notification
{
    private $payment = null;

    private $contact = null;

    function __construct($payment = null)
    {
        $this->payment              = $payment;
        $this->contact              = Config::get('orbit.contact_information');
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

    /**
     * Get the email data.
     * 
     * @return [type] [description]
     */
    protected function getEmailData()
    {
        $transaction = [];

        $amount = $this->payment->getAmount();

        $transaction['id']    = $this->payment->payment_transaction_id;
        $transaction['date']  = Carbon::parse($this->payment->transaction_date_and_time)->format('j M Y');
        $transaction['total'] = $amount;
        $redeemUrl            = Config::get('orbit.coupon.direct_redemption_url');
        $cs = [
            'phone' => $this->contact['customer_service']['phone'],
            'email' => $this->contact['customer_service']['email'],
        ];

        $transaction['items'] = [
            [
                'name'      => $this->payment->object_name,
                'quantity'  => 1,
                'price'     => $amount,
                'total'     => $amount, // should be quantity * $this->payment->amount
            ],
        ];

        return [
            'recipientEmail'    => $this->getEmailAddress(),
            'customerEmail'     => $this->getEmailAddress(),
            'customerName'      => $this->getName(),
            'customerPhone'     => $this->payment->phone,
            'transaction'       => $transaction,
            'redeemUrl'         => $redeemUrl,
            'cs'                => $cs,
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

            $emailTemplate = 'emails.coupon.sepulsa-customer-voucher-not-available';

            Mail::send($emailTemplate, $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = 'Coupon not Available';

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
            "Orbit\Notifications\Coupon\Sepulsa\VoucherNotAvailableNotification@toEmail", 
            $this->getEmailData(),
            $this->queueName
        );

        // Other notification method can be added here...
    }
}
