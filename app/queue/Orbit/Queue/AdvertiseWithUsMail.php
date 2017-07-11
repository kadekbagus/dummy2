<?php namespace Orbit\Queue;
/**
 * Process queue for sending advertise with us email
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

class AdvertiseWithUsMail
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Job $job
     * @param array $data [user_id => NUM]
     */
    public function fire($job, $data)
    {
        try {
            $mailviews = array(
                'html' => 'emails.advertise-with-us-email.advertise-with-us-html',
                'text' => 'emails.advertise-with-us-email.advertise-with-us-text'
            );

            $this->sendAdvertiseWithUsEmail($mailviews, $data);

            $message = sprintf('[Job ID: `%s`] Advertise with us mail; Status: Success;', $job->getJobId());
            Log::info($message);

            $job->delete();

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Advertise with us mail; Status: FAIL; Code: %s; Message: %s',
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
     * @param array $data
     * @return void
     */
    protected function sendAdvertiseWithUsEmail($mailviews, $data)
    {

        Mail::send($mailviews, $data, function($message) use ($data)
        {
            $from = Config::get('orbit.generic_email.sender.email', 'no-reply@gotomalls.com');
            $name = Config::get('orbit.generic_email.sender.name', 'Gotomalls');
            $subject = Config::get('orbit.advertise_with_us_email.subject', 'Advertise With Us');
            $email = Config::get('orbit.advertise_with_us_email.email_list');
            $message->from($from, $name);
            $message->subject($subject);
            $message->to($email);
        });
    }
}