<?php namespace Orbit\Queue;
/**
 * Process queue for sending user email after registration. This email
 * contains activation link.
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @author Irianto Pratama <irianto@dominopos.com>
 */
use User;
use Mail;
use Config;
use Token;
use Mall;
use TemporaryContent;
use News;
use Coupon;

class CampaignMail
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Job $job
     * @param array $data [user_id => NUM]
     */
    public function fire($job, $data)
    {
        // Get data information from the queue
        switch ($data['mode']) {
            case 'expired':
                    $mailviews = array(
                        'html' => 'emails.campaign-auto-email.campaign-expired-html',
                        'text' => 'emails.campaign-auto-email.campaign-expired-text'
                    );
                break;

            case 'update':
                    $temporaryContentId = $data['temporaryContentId'];
                    $tmpCampaign = TemporaryContent::where('temporary_content_id', $temporaryContentId)->first();

                    if (! is_object($tmpCampaign)) {
                        \Log::error('Temporary content not found.');
                        return ;
                    }

                    switch (ucfirst($data['campaignType'])) {
                        case 'News':
                        case 'Promotion':
                            $updatedCampaign = News::excludeDeleted()
                                                    ->where('news_id', $data['campaignId'])
                                                    ->first();
                            break;

                        case 'Coupon':
                        default:
                            $updatedCampaign = Coupon::excludeDeleted()
                                                    ->where('promotion_id', $data['campaignId'])
                                                    ->first();
                    }

                    if (! is_object($updatedCampaign)) {
                        \Log::error('Campaign not found.');
                        return ;
                    }

                    $mailviews = array(
                        'html' => 'emails.campaign-auto-email.campaign-update-html',
                        'text' => 'emails.campaign-auto-email.campaign-update-text'
                    );

                    $data['campaign_before'] = unserialize($tmpCampaign->contents);
                    \Log::info('cmpgnBfr: '. serialize($data['campaign_before']));
                    if ($data['campaignType'] === 'Coupon') {
                        $data['campaign_after'] = $updatedCampaign->load('translations.language', 'translations.media', 'ages.ageRange', 'genders', 'keywords', 'campaign_status', 'tenants', 'employee');
                    } else {
                        $data['campaign_after'] = $updatedCampaign->load('translations.language', 'translations.media', 'ages.ageRange', 'genders', 'keywords', 'campaign_status');
                    }

                break;

            case 'create':
            default:
                $mailviews = array(
                    'html' => 'emails.campaign-auto-email.campaign-html',
                    'text' => 'emails.campaign-auto-email.campaign-text'
                );
        }

        $this->sendCampaignEmail($mailviews, $data);

        // Don't care if the job success or not we will provide user
        // another link to resend the activation
        $job->delete();
    }

    /**
     * Common routine for sending email.
     *
     * @param array $data
     * @return void
     */
    protected function sendCampaignEmail($mailviews, $data)
    {

        Mail::send($mailviews, $data, function($message) use ($data)
        {
            $emailconf = Config::get('orbit.campaign_auto_email.sender');
            $from = $emailconf['email'];
            $name = $emailconf['name'];

            $email = Config::get('orbit.campaign_auto_email.email_list');

            if ($data['eventType'] === 'expired') {
                $subject = $data['campaignType'] .' - '. $data['campaignName'] .' is '. $data['eventType'];
            } else {
                $subject = $data['campaignType'] .' - '. $data['campaignName'] .' has just been '. $data['eventType'];
            }

            $message->from($from, $name);
            $message->subject($subject);
            $message->to($email);
        });
    }
}