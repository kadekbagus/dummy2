<?php namespace Orbit\Queue;
/**
 * Process queue for sending user email when they got a approved review
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */
use Mail;
use Config;
use Token;
use Orbit\Helper\Util\JobBurier;
use DB;
use Exception;
use ModelNotFoundException;
use Log;
use Coupon;
use Tenant;
use Mall;
use News;

class ReviewImageApprovalMailQueue
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     * @param Job $job
     */

    public function fire($job, $data)
    {
        $reviewId = $data['review_id'];
        $approval_type = $data['approval_type'];
        $reject_reason = $data['reject_reason'];
        $email =  $data['email'];
        $fullname =  $data['fullname'];
        $object_id =  $data['object_id'];
        $object_type =  $data['object_type'];
        $review =  $data['review'];
        $url_detail = $data['url_detail'];
        $subject = $data['subject'];

        try {
            // We do not check the validity of the issued coupon, because it
            // should be validated in code which calls this queue
            $message = 'Review ID: ' . $reviewId;
            $message = sprintf('[Job ID: `%s`] REVIEW IMAGE APPROVED QUEUE -- Status: OK -- Send email to: %s -- Review ID: %s -- Message: %s',
                                $job->getJobId(), $email, $reviewId, $message);

            $this->sendImageApprovalEmail([
                                        'email' => $email,
                                        'fullname' => $fullname,
                                        'review_id' => $reviewId,
                                        'object_id' => $object_id ,
                                        'object_type' => $object_type ,
                                        'review' => $review ,
                                        'url_detail' => $url_detail,
                                        'subject' => $subject,
                                        'approval_type' => $approval_type,
                                        'reject_reason' => $reject_reason,
                                        'store_name' => $data['store_name'],
                                        'mall_name' => $data['mall_name'],
                                    ]);

            $job->delete();

            Log::info($message);

            return ['status' => 'ok', 'message' => $message];
        } catch (ModelNotFoundException $e) {
            $message = sprintf('[Job ID: `%s`] REVIEW IMAGE APPROVED QUEUE -- Status: FAIL -- Send email to: %s -- Review ID: %s -- Message: %s -- File: %s -- Line: %s',
                                $job->getJobId(), $email, $reviewId,
                                $e->getMessage(), $e->getFile(), $e->getLine());
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] REVIEW IMAGE APPROVED QUEUE -- Status: FAIL -- Send email to: %s -- Review ID: %s -- Message: %s -- File: %s -- Line: %s',
                                $job->getJobId(), $email, $reviewId,
                                $e->getMessage(), $e->getFile(), $e->getLine());
        }

        // Bury the job for later inspection
        JobBurier::create($job, function($theJob) {
            // The queue driver does not support bury.
            $theJob->delete();
        })->bury();

        Log::info($message);
        return ['status' => 'fail', 'message' => $message];
    }

    /**
     * Sending email to user for image review approval
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     * @param array $data
     * @return void
     */
    protected function sendImageApprovalEmail($data)
    {
        $emailData = [
            'email' => $data['email'],
            'fullname' => $data['fullname'],
            'object_id' => $data['object_id'],
            'object_type' => $data['object_type'],
            'review' => $data['review'],
            'url_detail' => $data['url_detail'],
            'subject' => $data['subject'],
            'approval_type' => $data['approval_type'],
            'reject_reason' => $data['reject_reason'],
            'campaign_and_location_info' => $this->buildCampaignAndLocationInfo($data),
        ];

        $mailviews = 'emails.review-images-approval.approve';

        if ($data['approval_type'] == 'rejected') {
            $mailviews = 'emails.review-images-approval.reject';
        }

        Mail::send($mailviews, $emailData, function($message) use ($data)
        {
            $emailconf = Config::get('orbit.generic_email.sender');
            $from = $emailconf['email'];
            $name = $emailconf['name'];

            $subject = $data['subject'];

            $message->from($from, $name)->subject($subject);
            $message->to($data['email']);
        });
    }

    /**
     * Generate campaign and location info text.
     *
     * @return [type] [description]
     */
    private function buildCampaignAndLocationInfo($reviewData)
    {
        $campaignAndLocationInfo = '';

        $campaignId = $reviewData['object_id'];
        $campaignType = $reviewData['object_type'];

        $campaign = $this->getCampaign($campaignId, $campaignType);

        if (! empty($campaign)) {
            $campaignAndLocationInfo .= "{$campaign->object_name}";
        }

        if (! empty($reviewData['store_name']) && $campaignType !== 'store') {
            $campaignAndLocationInfo .= " on {$reviewData['store_name']}";
        }

        if (! empty($reviewData['mall_name']) && $campaignType !== 'mall') {
            $campaignAndLocationInfo .= " at {$reviewData['mall_name']}";
        }

        return $campaignAndLocationInfo;
    }

    /**
     * Get campaign detail.
     *
     * @return [type]               [description]
     */
    private function getCampaign($campaignId, $campaignType)
    {
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
