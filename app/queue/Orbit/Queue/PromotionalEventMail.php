<?php namespace Orbit\Queue;
/**
 * Process queue for sending user email after registration. This email
 * contains activation link.
 *
 * @author Kadek <kadek@dominopos.com>
 */
use User;
use Mail;
use Config;
use News;
use DB;
use Language;
use Str;
use App;
use Lang;
use UserReward;

class PromotionalEventMail
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

        $campaign =  News::select('reward_details.reward_detail_id',
                            DB::raw("
                            CASE WHEN ({$prefix}news_translations.news_name = ''
                                    or {$prefix}news_translations.news_name is null)
                                THEN default_translation.news_name
                                ELSE {$prefix}news_translations.news_name END as news_name,
                            CASE WHEN ({$prefix}reward_detail_translations.email_content = ''
                                    or {$prefix}reward_detail_translations.email_content is null)
                                THEN reward_default_translation.email_content
                                ELSE {$prefix}reward_detail_translations.email_content
                                END as email_content"))
                    ->join('reward_details', 'reward_details.object_id', '=', 'news.news_id')
                    ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                    ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                    ->leftJoin('news_translations', function ($q) use ($valid_language) {
                        $q->on('news_translations.news_id', '=', 'news.news_id')
                          ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                    })
                    ->leftJoin('news_translations as default_translation', function ($q) use ($prefix){
                        $q->on(DB::raw("default_translation.news_id"), '=', 'news.news_id')
                          ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                    })
                    ->leftJoin('reward_detail_translations', function ($q) use ($valid_language) {
                        $q->on('reward_detail_translations.reward_detail_id', '=', 'reward_details.reward_detail_id')
                          ->on('reward_detail_translations.language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                    })
                    ->leftJoin('reward_detail_translations as reward_default_translation', function ($q) use ($valid_language) {
                        $q->on(DB::raw("reward_default_translation.reward_detail_id"), '=', 'reward_details.reward_detail_id')
                          ->on(DB::raw("reward_default_translation.language_id"), '=', 'languages.language_id');
                    })
                    ->where('news.news_id', $data['campaignId'])
                    ->where('news.is_having_reward', '=', 'Y')
                    ->first();

        $userReward = UserReward::where('user_id', $data['userId'])
                                ->where('reward_detail_id', $campaign->reward_detail_id)
                                ->where('status', '!=', 'expired')
                                ->first();

        $user_full_name = $user->user_firstname . ' ' . $user->lastname;

        $arr_search = ['{{USER_FULL_NAME}}', '{{USER_EMAIL}}', '{{USER_CODE}}'];
        $arr_replace = [$user_full_name, $user->user_email, $userReward->reward_code];
        $message = str_replace($arr_search, $arr_replace, $campaign->email_content);

        $baseLinkUrl = Config::get('app.url') . '/?utm_source=gtm-share&utm_medium=email&utm_content=menulink#!/%s?lang=' . $data['languageId'];

        $dataView['message'] = $message;
        $dataView['email'] = $user->user_email;
        $dataView['campaignName'] = $campaign->news_name;
        $dataView['linkMalls']      = sprintf($baseLinkUrl, 'malls');
        $dataView['linkStores']     = sprintf($baseLinkUrl, 'stores');
        $dataView['linkPromotions'] = sprintf($baseLinkUrl, 'promotions');
        $dataView['linkCoupons']    = sprintf($baseLinkUrl, 'coupons');
        $dataView['linkEvents']     = sprintf($baseLinkUrl, 'events');
        $dataView['labelMalls'] = Lang::get('email.campaign_share.label_malls');
        $dataView['labelStores'] = Lang::get('email.campaign_share.label_stores');
        $dataView['labelPromotions'] = Lang::get('email.campaign_share.label_promotions');
        $dataView['labelCoupons'] = Lang::get('email.campaign_share.label_coupons');
        $dataView['labelEvents'] = Lang::get('email.campaign_share.label_events');

        $mailViews = array(
                    'html' => 'emails.promotional-event.promotional-event-html',
                    'text' => 'emails.promotional-event.promotional-event-text'
        );

        $this->sendPromotionalEventEmail($mailViews, $dataView);

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
    protected function sendPromotionalEventEmail($mailviews, $data)
    {

        Mail::send($mailviews, $data, function($message) use ($data)
        {
            $emailconf = Config::get('orbit.promotional_event_get_code_email.sender');
            $from = $emailconf['email'];
            $name = $emailconf['name'];

            $email = $data['email'];

            $subjectConfig = Config::get('orbit.promotional_event_get_code_email.subject');
            $subject = sprintf($subjectConfig, $data['campaignName']);

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