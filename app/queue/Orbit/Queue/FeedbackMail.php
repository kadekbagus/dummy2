<?php namespace Orbit\Queue;
/**
 * Process queue for sending customer support a feedback from user.
 *
 * @author Irianto Pratama <irianto@dominopos.com>
 */
use User;
use Mail;
use Config;

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
        // Get data information from the queue
        $cs_email   = $data['cs_email'];
        $user_email = $data['user_email'];
        $feedback   = $data['feedback'];
        $name       = $data['name'];
        $email      = $data['email'];

        $this->sendFeedbackEmail($cs_email, $user_email, $feedback, $name, $email);

        $job->delete();
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