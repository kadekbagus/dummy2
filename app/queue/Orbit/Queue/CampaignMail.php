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
use News;
use Coupon;
use DB;
use Log;
use Orbit\Helper\Util\JobBurier;
use Exception;


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
        try {
            $prefix = DB::getTablePrefix();
            // Get data information from the queue
            switch ($data['mode']) {
                case 'expired':
                        $mailviews = array(
                            'html' => 'emails.campaign-auto-email.campaign-expired-html',
                            'text' => 'emails.campaign-auto-email.campaign-expired-text'
                        );
                    break;

                case 'update':
                        switch (ucfirst($data['campaignType'])) {
                            case 'News':
                            case 'Promotion':
                                $updatedCampaign = News::selectRaw("{$prefix}news.*,
                                                            DATE_FORMAT({$prefix}news.end_date, '%d/%m/%Y %H:%i') as end_date")
                                                        ->excludeDeleted()
                                                        ->where('news_id', $data['campaignId'])
                                                        ->first();
                                break;

                            case 'Coupon':
                            default:
                                $updatedCampaign = Coupon::selectRaw("{$prefix}promotions.*,
                                                            DATE_FORMAT({$prefix}promotions.end_date, '%d/%m/%Y %H:%i') as end_date,
                                                            DATE_FORMAT({$prefix}promotions.coupon_validity_in_date, '%d/%m/%Y %H:%i') as coupon_validity_in_date,
                                                            IF({$prefix}promotions.maximum_issued_coupon = 0, 'Unlimited', {$prefix}promotions.maximum_issued_coupon) as maximum_issued_coupon
                                                        ")
                                                        ->excludeDeleted()
                                                        ->where('promotion_id', $data['campaignId'])
                                                        ->first();
                        }

                        if (! is_object($updatedCampaign)) {
                            \Log::error('*** CampaignMail Queue: Campaign not found. ***');
                            return ;
                        }

                        $mailviews = array(
                            'html' => 'emails.campaign-auto-email.campaign-update-html',
                            'text' => 'emails.campaign-auto-email.campaign-update-text'
                        );

                        if ($data['campaignType'] === 'Coupon') {
                            $data['campaign_after'] = $updatedCampaign->load([
                                'translations.language',
                                'translations.media',
                                'ages.ageRange',
                                'genders',
                                'keywords',
                                'campaign_status',
                                'tenants' => function($q) use($prefix) {
                                    $q->addSelect(DB::raw("CONCAT ({$prefix}merchants.name, ' at ', malls.name) as name"));
                                    $q->join(DB::raw("{$prefix}merchants malls"), DB::raw("malls.merchant_id"), '=', 'merchants.parent_id');
                                },
                                'employee',
                                'couponRule' => function($q) use($prefix) {
                                    $q->select('promotion_rule_id', 'promotion_id', DB::raw("DATE_FORMAT({$prefix}promotion_rules.rule_end_date, '%d/%m/%Y %H:%i') as rule_end_date"));
                                }
                            ]);
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

            $message = sprintf('[Job ID: `%s`] Campaign Mail; Status: Success;', $job->getJobId());
            Log::info($message);

            $job->delete();

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Campaign Mail; Status: FAIL; Code: %s; Message: %s',
                    $job->getJobId(),
                    $e->getCode(),
                    $e->getMessage());

            Log::info($message);
        }

        // Bury the job for later inspection
        JobBurier::create($job, function($theJob) {
            // The queue driver does not support bury.
            $theJob->delete();
        })->bury();

        return [
            'status' => 'fail',
            'message' => $message
        ];
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