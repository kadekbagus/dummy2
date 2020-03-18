<?php namespace Orbit\Notifications\Feedback;

use Config;
use Exception;
use Log;
use Mail;
use Orbit\Helper\Notifications\AdminNotification;

/**
 * Notify Customer Service (CS) for feedback related to Store.
 *
 * @author Budi <budi@dominopos.com>
 */
class StoreFeedbackNotification extends AdminNotification
{
    protected $shouldQueue = true;

    protected $feedback = [];

    function __construct($notifiable = null, $feedback = [])
    {
        parent::__construct($notifiable);
        $this->feedback = $feedback;
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'feedback'          => $this->feedback,
        ];
    }

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.feedback.store',
        ];
    }

    public function toEmail($job, $data)
    {
        try {
            Mail::send($this->getEmailTemplates(), $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = trans('email-feedback-store.subject');

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

        } catch (Exception $e) {
            Log::info('StoreFeedbackNotification: exception @ ' . $e->getFile() . ':' . $e->getLine() . ' >> ' . $e->getMessage());
            Log::info('StoreFeedbackNotification: email data: ' . serialize($data));
        }

        $job->delete();
    }
}
