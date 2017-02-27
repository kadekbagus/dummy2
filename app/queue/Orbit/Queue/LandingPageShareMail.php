<?php namespace Orbit\Queue;
/**
 * Process queue for sending user email after registration. This email
 * contains activation link.
 *
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
use Lang;
use App;

class LandingPageShareMail
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

        $user = User::where('user_id','=', $data['userId'])
                    ->first();

        if (! empty($data['language'])) {
            App::setLocale($data['language']);
        }

        $baseLinkUrl = Config::get('app.url') . '/?utm_source=gtm-share&utm_medium=email&utm_content=menulink#!/%s?lang=' . $data['language'];

        $dataView['linkMalls']      = sprintf($baseLinkUrl, 'malls');
        $dataView['linkStores']     = sprintf($baseLinkUrl, 'stores');
        $dataView['linkPromotions'] = sprintf($baseLinkUrl, 'promotions');
        $dataView['linkCoupons']    = sprintf($baseLinkUrl, 'coupons');
        $dataView['linkEvents']     = sprintf($baseLinkUrl, 'events');
        $dataView['email'] = $data['email'];
        $dataView['name'] = $user->user_firstname;
        $dataView['shareUrl'] = Config::get('orbit.landingpage_share_email.share_url');
        $dataView['videoUrl'] = Config::get('orbit.landingpage_share_email.video_url');
        $dataView['labelMalls'] = Lang::get('email.gotomalls_share.label_malls');
        $dataView['labelStores'] = Lang::get('email.gotomalls_share.label_stores');
        $dataView['labelPromotions'] = Lang::get('email.gotomalls_share.label_promotions');
        $dataView['labelCoupons'] = Lang::get('email.gotomalls_share.label_coupons');
        $dataView['labelEvents'] = Lang::get('email.gotomalls_share.label_events');
        $dataView['subject'] = Lang::get('email.gotomalls_share.subject');
        $dataView['greeting'] = Lang::get('email.gotomalls_share.greeting');
        $dataView['message1'] = Lang::get('email.gotomalls_share.message_part1');
        $dataView['message2'] = Lang::get('email.gotomalls_share.message_part2');
        $dataView['button_try_now']  = Lang::get('email.gotomalls_share.button_try_now');

        $mailViews = array(
                    'html' => 'emails.landingpage-share-email.landingpage-share-html',
                    'text' => 'emails.landingpage-share-email.landingpage-share-text'
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
            $emailconf = Config::get('orbit.landingpage_share_email.sender');
            $from = $emailconf['email'];
            $name = $emailconf['name'];
            $email = $data['email'];
            $subject = $data['subject'];

            $message->from($from, $name);
            $message->subject($subject);
            $message->to($email);
        });
    }

}