<?php namespace Orbit\Queue;
/**
 * Process queue for sending customer support a feedback from user.
 *
 * @author Irianto Pratama <irianto@dominopos.com>
 */
use User;
use Mail;
use Config;
use Log;
use Orbit\Helper\Util\JobBurier;
use Exception;

class FeedbackMail
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Irianto <irianto@dominopos.com>
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
            $cs_email   = $data['cs_email'];
            $user_email = $data['user_email'];
            $feedback   = $data['feedback'];
            $name       = $data['name'];
            $email      = $data['email'];

            $this->sendFeedbackEmail($cs_email, $user_email, $feedback, $name, $email);

            $message = sprintf('[Job ID: `%s`] Feedback Mail; Status: Success;', $job->getJobId());
            Log::info($message);

            $job->delete();

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Feedback Mail; Status: FAIL; Code: %s; Message: %s',
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
     * @param string $cs_email customer support email.
     * @param string $user_email user email.
     * @param string $feedback feedback from customer.
     * @return void
     */
    protected function sendFeedbackEmail($cs_email, $user_email, $feedback, $name, $email)
    {
        $data = array(
            'user_email' => $user_email,
            'feedback'   => $feedback,
            'name'       => $name,
            'email'      => $email,
        );

        $mailviews = array(
            'html' => 'emails.feedback.feedback-html',
            'text' => 'emails.feedback.feedback-text'
        );
        Mail::send($mailviews, $data, function($message) use ($cs_email)
        {
            $emailconf = Config::get('orbit.registration.mobile.sender');
            $from = $emailconf['email'];
            $name = $emailconf['name'];

            $message->from($from, $name)->subject('Feedback from Customers');
            $message->to($cs_email);
        });
    }
}