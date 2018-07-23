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
     * @return string
     */
    protected function getRecipientEmail();

    /**
     * Get the recipient name.
     * @return [type] [description]
     */
    protected function getRecipientName();

    /**
     * Get the email data.
     * 
     * @return array
     */
    protected function getEmailData();

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
