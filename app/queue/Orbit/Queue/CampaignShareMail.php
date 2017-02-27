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
use Str;
use App;
use Lang;

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

        $langParam = '';
        if (! empty($data['languageId'])) {
            App::setLocale($data['languageId']);
            $langParam = '&lang=' . $data['languageId'];
        }

        $user = User::where('user_id','=', $data['userId'])
                    ->first();

        $countryCityParams = '';
        $countryString = '';
        if (! empty($data['country'])) {
            $countryString .= '&country=' . $data['country'];
        } else {
            $countryString .= '&country=0';
        }

        $citiesString = '';

        if (empty($data['cities'])) {
            $citiesString .= '&cities=0';
        } else {
            foreach ((array) $data['cities'] as $city) {
                $citiesString .= '&cities=' . $city;
            }
        }

        if (! empty($countryString)) {
            $countryCityParams = $countryString . $citiesString;
        }

        $utmParamConfig = Config::get('orbit.campaign_share_email.utm_params', null);
        $utmParam = isset($utmParamConfig['email']) ? http_build_query($utmParamConfig['email']) : '';

        $param = $utmParam . $countryCityParams . $langParam;

        switch($data['campaignType']) {
            case 'promotion' :
                   $campaign = News::select(
                                    'news.news_id as campaign_id',
                                    DB::Raw("
                                        CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as campaign_name,
                                        CASE WHEN {$prefix}media.path is null THEN (
                                                select m.path
                                                from {$prefix}media m
                                                where m.object_id = default_translation.news_translation_id
                                                    and m.media_name_long = 'news_translation_image_orig'
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
                                ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                                ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                                ->leftJoin('news_translations as default_translation', function ($q) {
                                    $q->on(DB::raw('default_translation.merchant_language_id'), '=', 'languages.language_id')
                                      ->on(DB::raw('default_translation.news_id'), '=', 'news.news_id');
                                })
                                ->where('news.news_id', $data['campaignId'])
                                ->where('news.object_type', '=', 'promotion')
                                ->first();

                    $baseUrl = Config::get('orbit.campaign_share_email.promotion_detail_base_url');
                    $campaignUrl = sprintf($baseUrl, $campaign->campaign_id, $this->getSlugUrl($campaign->campaign_name), $param);
                    $message2 = Lang::get('email.campaign_share.message_part2_promotion');
                    $campaignType = Lang::get('email.campaign_share.campaign_type_promotion');

                    break;

            case 'news' :
                   $campaign = News::select(
                                    'news.news_id as campaign_id',
                                    DB::Raw("
                                        CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as campaign_name,
                                        CASE WHEN {$prefix}media.path is null THEN (
                                                select m.path
                                                from {$prefix}media m
                                                where m.object_id = default_translation.news_translation_id
                                                    and m.media_name_long = 'news_translation_image_orig'
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
                                ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                                ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                                ->leftJoin('news_translations as default_translation', function ($q) {
                                    $q->on(DB::raw('default_translation.merchant_language_id'), '=', 'languages.language_id')
                                      ->on(DB::raw('default_translation.news_id'), '=', 'news.news_id');
                                })
                                ->where('news.news_id', $data['campaignId'])
                                ->where('news.object_type', '=', 'news')
                                ->first();

                    $baseUrl = Config::get('orbit.campaign_share_email.news_detail_base_url');
                    $campaignUrl = sprintf($baseUrl, $campaign->campaign_id, $this->getSlugUrl($campaign->campaign_name), $param);
                    $message2 = Lang::get('email.campaign_share.message_part2_event');
                    $campaignType = Lang::get('email.campaign_share.campaign_type_event');

                    break;

            case 'coupon' :
                    $campaign = Coupon::select(
                            'promotions.promotion_id as campaign_id',
                            DB::Raw("
                                    CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as campaign_name,
                                    CASE WHEN {$prefix}media.path is null THEN (
                                            select m.path
                                            from {$prefix}media m
                                            where m.object_id = default_translation.coupon_translation_id
                                                and m.media_name_long = 'coupon_translation_image_orig'
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
                        ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                        ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                        ->leftJoin('coupon_translations as default_translation', function ($q) {
                            $q->on(DB::raw('default_translation.promotion_id'), '=', 'promotions.promotion_id')
                              ->on(DB::raw('default_translation.merchant_language_id'), '=', 'languages.language_id');
                        })
                        ->where('promotions.promotion_id', $data['campaignId'])
                        ->first();

                    $baseUrl = Config::get('orbit.campaign_share_email.coupon_detail_base_url');
                    $campaignUrl = sprintf($baseUrl, $campaign->campaign_id, $this->getSlugUrl($campaign->campaign_name), $param);
                    $message2 = Lang::get('email.campaign_share.message_part2_coupon');
                    $campaignType = Lang::get('email.campaign_share.campaign_type_coupon');

                    break;
            default :
                    $campaign = null;
        }

        if (empty($campaign->original_media_path)) {
            $campaignImage = 'emails/campaign-default-picture.png';
        } else {
            $campaignImage = $campaign->original_media_path;
        }

        $baseLinkUrl = Config::get('app.url') . '/?utm_source=gtm-share&utm_medium=email&utm_content=menulink#!/%s?lang=' . $data['languageId'];

        $dataView['linkMalls']      = sprintf($baseLinkUrl, 'malls');
        $dataView['linkStores']     = sprintf($baseLinkUrl, 'stores');
        $dataView['linkPromotions'] = sprintf($baseLinkUrl, 'promotions');
        $dataView['linkCoupons']    = sprintf($baseLinkUrl, 'coupons');
        $dataView['linkEvents']     = sprintf($baseLinkUrl, 'events');
        $dataView['campaignName'] = $campaign->campaign_name;
        $dataView['campaignType'] = $campaignType;
        $dataView['campaignImage'] = $campaignImage;
        $dataView['campaignUrl'] = $campaignUrl;
        $dataView['email'] = $data['email'];
        $dataView['name'] = $user->user_firstname;
        $dataView['labelMalls'] = Lang::get('email.campaign_share.label_malls');
        $dataView['labelStores'] = Lang::get('email.campaign_share.label_stores');
        $dataView['labelPromotions'] = Lang::get('email.campaign_share.label_promotions');
        $dataView['labelCoupons'] = Lang::get('email.campaign_share.label_coupons');
        $dataView['labelEvents'] = Lang::get('email.campaign_share.label_events');
        $dataView['greeting'] = Lang::get('email.campaign_share.greeting');
        $dataView['message1'] = Lang::get('email.campaign_share.message_part1');
        $dataView['message2'] = $message2;
        $dataView['buttonSeeNow'] = Lang::get('email.campaign_share.button_see_now');

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

            $subjectConfig = Config::get('orbit.campaign_share_email.subject');
            $subject = sprintf($subjectConfig, ucfirst($data['campaignType']), $data['campaignName']);

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

    /**
     * This function make common slugify url, and remove the '%' to ''
     */
    protected function getSlugUrl($campaign_name)
    {
        $slug = Str::slug($campaign_name, $separator = '-');
        $slugCampaignName = str_replace('%', '', $slug);
        return $slugCampaignName;
    }

}