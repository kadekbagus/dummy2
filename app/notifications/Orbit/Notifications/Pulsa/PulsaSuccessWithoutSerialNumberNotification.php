<?php namespace Orbit\Notifications\Pulsa;

use Orbit\Helper\Notifications\AdminNotification;
use Orbit\Helper\Util\JobBurier;
use Carbon\Carbon;

use Orbit\Notifications\Pulsa\PulsaNotAvailableNotification as BaseNotification;
use Orbit\Notifications\Traits\HasPaymentTrait as HasPayment;

use Mail;
use Config;
use Log;
use Queue;
use Exception;

/**
 * Notify Admin that the pulsa purchase is success but without Serial Number.
 *
 * @author Budi <budi@dominopos.com>
 */
class PulsaSuccessWithoutSerialNumberNotification extends BaseNotification
{
    /**
     * Get the email templates.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.pulsa.admin-pulsa-success-without-sn',
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
            $blacklistedEmails = [
                'sputraqu@yahoo.com'
            ];

            if (in_array($data['recipientEmail'], $blacklistedEmails)) {
                $job->delete();
                return;
            }

            Log::info("notifiable: " . serialize($this->notifiable));
            Mail::send($this->getEmailTemplates(), $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = trans('email-pulsa-admin.subject_pulsa_success_without_sn');

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

        } catch (Exception $e) {
            Log::info('PulsaSuccessWithoutSerialNumberNotification: email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
            Log::info('PulsaSuccessWithoutSerialNumberNotification: email data: ' . serialize($data));
        }

        $job->delete();
    }
}
