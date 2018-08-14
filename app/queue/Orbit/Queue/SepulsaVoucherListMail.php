<?php namespace Orbit\Queue;
/**
 * Process queue for sending email from sepulsa get voucher list artisan command
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use User;
use Mail;
use Config;
use Log;
use Orbit\Helper\Util\JobBurier;
use Exception;

class SepulsaVoucherListMail
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @param Job $job
     * @param array $data [
                cs_email => string,
                feedback => string,
                user_email => string
            ]
     */
    public function fire($job, $data)
    {
        try {
            // Get data information from the queue
            $attachment = $data['attachment'];
            $emails = $data['emails'];
            $from = $data['from'];
            $name = 'Gotomalls Robot';
            $subject = 'Sepulsa Voucher List';

            $this->sendEmail($attachment, $emails, $from, $name, $subject);

            $message = sprintf('[Job ID: `%s`] Sending Sepulsa Vouchers List Mail; Status: Success;', $job->getJobId());
            Log::info($message);

            $job->delete();

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Sending Sepulsa Vouchers List Mail; Status: FAIL; Code: %s; Message: %s',
                    $job->getJobId(),
                    $e->getCode(),
                    $e->getMessage());
            Log::info($message);
        }

        // Bury the job for later inspection
        JobBurier::create($job, function($theJob) {
            // The queue driver does not support bury.
            $theJob->delete();
        })->bury();

        return [
            'status' => 'fail',
            'message' => $message
        ];
    }

    /**
     * Common routine for sending email.
     *
     * @return void
     */
    protected function sendEmail($attachment, $emails, $from, $name, $subject)
    {
        Mail::send('emails.sepulsa-voucher-list.html', [], function($message) use ($data)
        {
            $message->from($from, $name)->subject($subject);
            $message->to($emails);
            $message->attach($attachment);
        });
    }
}