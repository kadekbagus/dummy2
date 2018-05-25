<?php namespace Orbit\Notifications\Sepulsa;

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
 * Notify user after completing and getting Sepulsa Voucher.
 * 
 */
class InvoiceNotification extends Notification
{
    protected $payment = null;

    function __construct($payment = null)
    {
        $this->payment = $payment;
        $this->queueName = Config::get('orbit.registration.mobile.queue_name');
    }

    /**
     * Custom field email address if not reading from field 'email'
     * 
     * @return [type] [description]
     */
    protected function getEmailAddress()
    {
        return $this->payment->user_email;
    }

    /**
     * Custom name if not reading from field 'name'.
     * 
     * @return [type] [description]
     */
    protected function getName()
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

        $transaction['items'] = [
            [
                'name'      => $this->payment->object_name,
                'quantity'  => '1',
                'price'     => $amount,
                'total'     => $amount, // should be quantity * $this->payment->amount
            ],
        ];

        return [
            'customerEmail'     => $this->getEmailAddress(),
            'customerName'      => $this->getName(),
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
            // Log::debug('Notification(' . $data['transactionId'] . '): Sending InvoiceNotification email...');

            $emailTemplate = 'emails.receipt.sepulsa-voucher';

            Mail::send($emailTemplate, $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = 'Your Invoice from Gotomalls.com';

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['customerEmail']);
            });

            $job->delete();

            // Log::debug('Notification(' . $data['transactionId'] . '): InvoiceNotification email should be sent by now.');

            // Bury the job for later inspection
            JobBurier::create($job, function($theJob) {
                // The queue driver does not support bury.
                $theJob->delete();
            })->bury();

        } catch (Exception $e) {
            Log::debug('Notification: InvoiceNotification email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
        }
    }

    /**
     * Notify to web / user's notification.
     * MongoDB record, appear in user's notifications list page
     * 
     * @return [type] [description]
     */
    public function toWeb()
    {
        
    }

    /**
     * Send notification.
     * 
     * @return [type] [description]
     */
    public function send()
    {
        // Log::debug('Notification: Pushing InvoiceNotification email to queue..');
        Queue::push(
            'Orbit\\Notifications\\Sepulsa\\InvoiceNotification@toEmail', 
            $this->getEmailData(),
            $this->queueName
        );

        // Other notification method can be added here...
    }
}
