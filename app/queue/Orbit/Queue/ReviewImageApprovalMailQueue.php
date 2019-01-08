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
        ];

        $mailviews = [
            'html' => 'emails.review-images-approval.approve'
        ];

        if ($data['approval_type'] == 'rejected') {
            $mailviews = [
                'html' => 'emails.review-images-approval.reject'
            ];
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
}