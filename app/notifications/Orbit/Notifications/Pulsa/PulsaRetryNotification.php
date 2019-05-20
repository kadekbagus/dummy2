<?php namespace Orbit\Notifications\Pulsa;

use Orbit\Notifications\Pulsa\PulsaNotAvailableNotification as BaseNotification;

use Mail;
use Config;
use Log;
use Exception;

/**
 * Notify Admin that system will retry pulsa purchase in a few minutes.
 *
 * @author Budi <budi@dominopos.com>
 */
class PulsaRetryNotification extends BaseNotification
{
    /**
     * Get the email templates.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.pulsa.admin-pulsa-retry',
        ];
    }

    /**
     * Get email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'reason' => $this->reason,
        ]);
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
            Mail::send($this->getEmailTemplates(), $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = trans('email-coupon-not-available-admin.subject_pulsa_retrying');

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

        } catch (Exception $e) {
            Log::info('PulsaPendingNotification: email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
            Log::info('PulsaPendingNotification: email data: ' . serialize($data));
        }

        $job->delete();
    }
}
