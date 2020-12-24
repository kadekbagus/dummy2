<?php namespace Orbit\Helper\Notifications\Contracts;

/**
 * Contract for notification via email.
 *
 * @author Budi <budi@dominopos.com>
 */
interface EmailNotificationInterface
{
    /**
     * Get the recipient email address.
     *
     * @return array|string
     */
    public function getRecipientEmail();

    /**
     * Get the email templates that will be used.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates();

    /**
     * Get the email data.
     *
     * @return array
     */
    public function getEmailData();

    /**
     * We need $job and $data because this method
     * will act as a custom Queue handler.
     *
     * @param  Illuminate\Queue\Job $job  [description]
     * @param  array $data [description]
     * @return void
     */
    public function toEmail($job, $data);

}
