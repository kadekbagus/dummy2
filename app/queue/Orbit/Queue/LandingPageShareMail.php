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

        $dataView['email'] = $data['email'];
        $dataView['name'] = $user->user_firstname;
        $dataView['shareUrl'] = Config::get('orbit.landingpage_share_email.share_url');
        $dataView['videoUrl'] = Config::get('orbit.landingpage_share_email.video_url');

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

            $subject = Config::get('orbit.landingpage_share_email.subject');

            $message->from($from, $name);
            $message->subject($subject);
            $message->to($email);
        });
    }

}