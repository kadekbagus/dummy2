<?php namespace Orbit\Queue\Order;
/**
 * Process queue for sending order complete email
 *
 * @author kadek <kadek@dominopos.com>
 */
use Mail;
use Config;
use Token;
use DB;
use Log;
use Orbit\Helper\Util\JobBurier;
use Exception;

class OrderCompleteMailQueue
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @param Job $job
     * @param array $data [user_id => NUM]
     */
    public function fire($job, $data)
    {
        try {
            $mailviews = array(
                'html' => 'emails.order.admin.complete-order',
            );

            $this->sendEmail($mailviews, $data);

            $message = sprintf('[Job ID: `%s`] Order Complete mail; Status: Success;', $job->getJobId());
            Log::info($message);

            $job->delete();

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Order Complete mail; Status: FAIL; Code: %s; Message: %s Line: %s',
                    $job->getJobId(),
                    $e->getCode(),
                    $e->getMessage(), 
                    $e->getLine()
                );
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
     * @param array $data
     * @return void
     */
    protected function sendEmail($mailviews, $data)
    {

        Mail::send($mailviews, $data, function($message) use ($data)
        {
            $from = Config::get('orbit.generic_email.sender.email', 'no-reply@gotomalls.com');
            $name = Config::get('orbit.generic_email.sender.name', 'Gotomalls');
            $subject = $data['emailSubject'];
            $email = $data['recipientEmail'];
            $message->from($from, $name);
            $message->subject($subject);
            $message->to($email);
        });
    }
}