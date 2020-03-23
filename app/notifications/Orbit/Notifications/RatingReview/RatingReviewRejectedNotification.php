<?php namespace Orbit\Notifications\RatingReview;

use DB;
use Mail;
use Config;
use Log;
use Exception;
use User;
use Tenant;
use Mall;
use Coupon;
use News;
use Carbon\Carbon;

use Orbit\Helper\Notifications\CustomerNotification;
use Orbit\Helper\Notifications\Contracts\EmailNotificationInterface;

/**
 * Notification to GTM user if rating/review rejected by admin.
 *
 * @author Budi <budi@dominopos.com>
 */
class RatingReviewRejectedNotification extends CustomerNotification implements EmailNotificationInterface
{
    protected $shouldQueue = true;

    protected $ratingReview = null;

    function __construct($ratingReview = null)
    {
        $this->ratingReview = $ratingReview;
    }

    /**
     * Get the email templates.
     * At the moment we can use same template for both Sepulsa and Hot Deals.
     * Can be overriden in each receipt class if needed.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.rating.reject-review-html',
            'text' => 'emails.rating.reject-review-text',
        ];
    }

    public function getRecipientEmail()
    {
        return '';
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        $prefix = DB::getTablePrefix();
        $user = User::select('users.user_email', DB::raw("(CONCAT({$prefix}users.user_firstname, ' ', {$prefix}users.user_lastname)) as user_name"))
                                  ->where('users.user_id', $this->ratingReview->user_id)
                                  ->firstOrFail();

        $emailData = [
            'fullname' => $user->user_name,
            'campaignAndLocationInfo' => $this->buildCampaignAndLocationInfo(),
            'recipientEmail' => $user->user_email,
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

                $subject = trans('rating.email.rejected.subject');

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });
        } catch (Exception $e) {
            Log::debug('Notification: RatingReviewRejectedNotification email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());

            // Rethrow exception to the caller command/class.
            throw new Exception("Error");
        }

        $job->delete();
    }

    /**
     * Generate campaign and location info text.
     *
     * @return [type] [description]
     */
    private function buildCampaignAndLocationInfo()
    {
        $campaignAndLocationInfo = '';

        $campaign = $this->getCampaign();

        if (! empty($campaign)) {
            $campaignAndLocationInfo .= "{$campaign->object_name}";
        }

        if (! empty($this->ratingReview->store_name) && $this->ratingReview->object_type !== 'store') {
            $campaignAndLocationInfo .= " on {$this->ratingReview->store_name}";
        }

        if (! empty($this->ratingReview->mall_name) && $this->ratingReview->object_type !== 'mall') {
            $campaignAndLocationInfo .= " at {$this->ratingReview->mall_name}";
        }

        return $campaignAndLocationInfo;
    }

    /**
     * Get campaign detail.
     *
     * @return [type]               [description]
     */
    private function getCampaign()
    {
        $campaignId = $this->ratingReview->object_id;
        $campaignType = $this->ratingReview->object_type;

        switch(strtolower($campaignType)) {
            case 'coupon':
                $campaign = Coupon::select('promotion_name as object_name')->where('promotion_id', '=', $campaignId)->first();
                break;
            case 'store':
                $campaign = Tenant::select('name as object_name')->where('merchant_id', '=', $campaignId)->first();
                break;
            case 'mall':
                $campaign = Mall::select('name as object_name')->where('merchant_id', '=', $campaignId)->first();
                break;
            default:
                $campaign = News::select('news_name as object_name')->where('news_id', '=', $campaignId)->first();
        }

        return $campaign;
    }
}
