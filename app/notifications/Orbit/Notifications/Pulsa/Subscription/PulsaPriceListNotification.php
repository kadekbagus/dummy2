<?php namespace Orbit\Notifications\Pulsa\Subscription;

use DB;
use Mail;
use Config;
use Log;
use Exception;
use User;
use Mall;
use Carbon\Carbon;

use Orbit\Notifications\Traits\HasContactTrait;

use Orbit\Helper\Notifications\CustomerNotification;
use Orbit\Helper\Notifications\Contracts\EmailNotificationInterface;

/**
 * Notify GTM User for Pulsa/Data Plan price list.
 *
 * @author Budi <budi@dominopos.com>
 */
class PulsaPriceListNotification extends CustomerNotification implements EmailNotificationInterface
{
    use HasContactTrait;

    protected $shouldQueue = true;

    protected $campaigns;

    function __construct($user = null, $campaigns = null)
    {
        parent::__construct($user);
        $this->campaigns = $campaigns;
    }

    /**
     * Get the email templates.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.subscription.pulsa-data-plan-list-html',
            'text' => 'emails.subscription.pulsa-data-plan-list-text',
        ];
    }

    public function getRecipientEmail()
    {
        return $this->notifiable->user_email;
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        $emailData = [
            'subject' => trans('email-subscription.pulsa.subject', [], '', 'id'),
            'customerName' => $this->notifiable->user_firstname . ' ' . $this->notifiable->user_lastname,
            'cs' => $this->getContactData(),
            'recipientEmail' => $this->getRecipientEmail(),
            'pulsaListUrl' => $this->getPulsaListUrl(),
            'gameVoucherUrl' => $this->getGameVoucherUrl(),
            'campaigns' => $this->campaigns,
        ];

        return $emailData;
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

                $mail->subject($data['subject']);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });
        } catch (Exception $e) {
            Log::debug('PulsaListNotification: Email exception. File: ' . $e->getFile() . '::' . $e->getLine() . ', Message: ' . $e->getMessage());
        }

        $job->delete();
    }

    /**
     * Get GTM Pulsa/Data Plan listing page url.
     *
     * @return [type] [description]
     */
    private function getPulsaListUrl()
    {
        $pulsaListUrl = Config::get('orbit.base_landing_page_url', 'https://www.gotomalls.com');
        $pulsaListUrlConfig = Config::get('orbit.pulsa_list_url', []);
        if (! empty($pulsaListUrlConfig)) {
            $pulsaListUrl .= $pulsaListUrlConfig['base_url'];
            foreach($pulsaListUrlConfig['utm_params'] as $key => $value) {
                $pulsaListUrl .= "&{$key}={$value}";
            }
        }

        return $pulsaListUrl;
    }

    /**
     * Get GTM Game voucher page url.
     *
     * @return string gtm game voucher page.
     */
    public function getGameVoucherUrl()
    {
        $url = Config::get('orbit.base_landing_page_url', 'https://www.gotomalls.com')
            . '/game-voucher?country=Indonesia';

        return $url;
    }
}
