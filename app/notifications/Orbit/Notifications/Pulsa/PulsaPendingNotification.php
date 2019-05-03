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
 * Notify Admin that the coupon we try to issue is not available.
 *
 * @author Budi <budi@dominopos.com>
 */
class PulsaPendingNotification extends BaseNotification
{
    /**
     * Get the email templates.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.pulsa.admin-pulsa-pending',
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
            Mail::send($this->getEmailTemplates(), $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = trans('email-coupon-not-available-admin.subject_pulsa_pending');

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
