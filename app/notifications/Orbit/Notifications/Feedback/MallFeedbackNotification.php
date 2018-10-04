<?php namespace Orbit\Notifications\Payment;

use Config;
use Exception;
use Log;
use Mail;
use Orbit\Helper\Notifications\AdminNotification;
use Orbit\Helper\Util\JobBurier;
use Queue;

/**
 * Notify Customer Service (CS) for feedback related to Mall.
 *
 * @author Budi <budi@dominopos.com>
 *
 */
class MallFeedbackNotification extends AdminNotification
{
    protected $shouldQueue = true;

    protected $feedback = [];

    function __construct($feedback = [])
    {
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
            'html' => 'emails.feedback.mall',
        ];
    }

    public function toEmail($job, $data)
    {
        try {
            Mail::send($this->getEmailTemplates(), $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = 'User Report for Mall';

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

        } catch (Exception $e) {
            Log::info('MallFeedbackNotification: exception @ ' . $e->getFile() . ':' . $e->getLine() . ' >> ' . $e->getMessage());
            Log::info('MallFeedbackNotification: email data: ' . serialize($data));
        }

        $job->delete();
    }
}
