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
use DB;
use Language;

class CampaignShareMail
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
        $prefix = DB::getTablePrefix();

        $valid_language = Language::where('status', '=', 'active')
                            ->where('name', $data['languageId'])
                            ->first();

        switch($data['campaignType']) {
            case 'promotion' :
                   $campaign = News::select(
                                    'news.news_id as campaign_id',
                                    DB::Raw("
                                        CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN {$prefix}news.news_name ELSE {$prefix}news_translations.news_name END as campaign_name,
                                        CASE WHEN {$prefix}media.path is null THEN (
                                                select m.path
                                                from {$prefix}news_translations nt
                                                join {$prefix}media m
                                                    on m.object_id = nt.news_translation_id
                                                    and m.media_name_long = 'news_translation_image_orig'
                                                where nt.news_id = {$prefix}news.news_id
                                                group by nt.news_id
                                            ) ELSE {$prefix}media.path END as original_media_path
                                    ")
                                )
                                ->leftJoin('news_translations', function ($q) use ($valid_language) {
                                    $q->on('news_translations.news_id', '=', 'news.news_id')
                                      ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                                })
                                ->leftJoin('media', function ($q) {
                                    $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                                    $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                                })
                                ->where('news.news_id', $data['campaignId'])
                                ->where('news.object_type', '=', 'promotion')
                                ->first();
                    break;

            case 'news' :
                   $campaign = News::select(
                                    'news.news_id as campaign_id',
                                    DB::Raw("
                                        CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN {$prefix}news.news_name ELSE {$prefix}news_translations.news_name END as campaign_name,
                                        CASE WHEN {$prefix}media.path is null THEN (
                                                select m.path
                                                from {$prefix}news_translations nt
                                                join {$prefix}media m
                                                    on m.object_id = nt.news_translation_id
                                                    and m.media_name_long = 'news_translation_image_orig'
                                                where nt.news_id = {$prefix}news.news_id
                                                group by nt.news_id
                                            ) ELSE {$prefix}media.path END as original_media_path
                                    ")
                                )
                                ->leftJoin('news_translations', function ($q) use ($valid_language) {
                                    $q->on('news_translations.news_id', '=', 'news.news_id')
                                      ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                                })
                                ->leftJoin('media', function ($q) {
                                    $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                                    $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                                })
                                ->where('news.news_id', $data['campaignId'])
                                ->where('news.object_type', '=', 'news')
                                ->first();

                    break;

            case 'coupon' :
                    $campaign = Coupon::select(
                            'promotions.promotion_id as campaign_id',
                            DB::Raw("
                                    CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN {$prefix}promotions.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as campaign_name,
                                    CASE WHEN {$prefix}media.path is null THEN (
                                            select m.path
                                            from {$prefix}coupon_translations ct
                                            join {$prefix}media m
                                                on m.object_id = ct.coupon_translation_id
                                                and m.media_name_long = 'coupon_translation_image_orig'
                                            where ct.promotion_id = {$prefix}promotions.promotion_id
                                            group by ct.promotion_id
                                        ) ELSE {$prefix}media.path END as original_media_path
                                ")
                        )
                        ->leftJoin('coupon_translations', function ($q) use ($valid_language) {
                            $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                              ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })
                        ->leftJoin('media', function ($q) {
                            $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                            $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                        })
                        ->where('promotions.promotion_id', $data['campaignId'])
                        ->first();

                    break;
            default :
                    $campaign = null;
        }

        //$dataView['campaign_name'] = $campaign->campaign_name;
        //$dataView['campaign_name'] = $campaign->campaign_name;

        $mailViews = array(
                    'html' => 'emails.campaign-share-email.campaign-share-html',
                    'text' => 'emails.campaign-share-email.campaign-share-text'
        );

        $this->sendCampaignEmail($mailViews, $dataView);

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
    protected function sendCampaignShareEmail($mailviews, $data)
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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}