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
     * @author kadek <kadek@dominopos.com>
     * @param Job $job
     * @param array $data [user_id => NUM]
     */
    public function fire($job, $data)
    {
        $prefix = DB::getTablePrefix();

        $valid_language = Language::where('status', '=', 'active')
                            ->where('name', $data['languageId'])
                            ->first();

        $user = User::where('user_id','=', $data['userId'])
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

                    $campaign_url = Config::get('orbit.campaign_share_email.promotion_detail_base_url').$campaign->campaign_id.'/'.$this->getUrl($campaign->campaign_name);

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

                    $campaign_url = Config::get('orbit.campaign_share_email.news_detail_base_url').$campaign->campaign_id.'/'.$this->getUrl($campaign->campaign_name);

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

                    $campaign_url = Config::get('orbit.campaign_share_email.coupon_detail_base_url').$campaign->campaign_id.'/'.$this->getUrl($campaign->campaign_name);

                    break;
            default :
                    $campaign = null;
        }

        $campaign_image = Config::get('orbit.campaign_share_email.mall_api_base_url').$campaign->original_media_path;

        $dataView['campaignName'] = $campaign->campaign_name;
        $dataView['campaignType'] = $data['campaignType'];
        $dataView['campaignImage'] = $campaign_image;
        $dataView['campaignUrl'] = $campaign_url;
        $dataView['email'] = $data['email'];
        $dataView['name'] = $user->user_firstname;

        $mailViews = array(
                    'html' => 'emails.campaign-share-email.campaign-share-html',
                    'text' => 'emails.campaign-share-email.campaign-share-text'
        );

        $this->sendCampaignShareEmail($mailViews, $dataView);

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
            $emailconf = Config::get('orbit.campaign_share_email.sender');
            $from = $emailconf['email'];
            $name = $emailconf['name'];

            $email = $data['email'];

            $subject = Config::get('orbit.campaign_share_email.subject');

            $message->from($from, $name);
            $message->subject($subject);
            $message->to($email);
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    protected function getUrl($string)
    {
        //Lower case everything
        $string = strtolower($string);
        //Make alphanumeric (removes all other characters)
        $string = preg_replace("/[^a-z0-9_\s-]/", "", $string);
        //Clean up multiple dashes or whitespaces
        $string = preg_replace("/[\s-]+/", " ", $string);
        //Convert whitespaces and underscore to dash
        $string = preg_replace("/[\s_]/", "-", $string);
        return $string;
    }
}