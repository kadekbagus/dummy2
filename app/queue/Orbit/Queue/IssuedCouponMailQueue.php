<?php namespace Orbit\Queue;
/**
 * Process queue for sending user email when they got a coupon. This email
 * contains a link to redeem the coupon.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Mail;
use Config;
use Token;
use IssuedCoupon;
use Orbit\Helper\Util\JobBurier;
use DB;
use Exception;
use ModelNotFoundException;
use Log;
use Coupon;

class IssuedCouponMailQueue
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param Job $job
     * @param array $data [
     *          issued_coupon_id => NUM,
     *          redeem_url => STRING,
     *          email => STRING
     * ]
     */
    public function fire($job, $data)
    {
        $couponId = $data['coupon_id'];
        $email = $data['email'];
        $user = new \stdClass();
        $user->user_email = $email;

        try {
            // We do not check the validity of the issued coupon, because it
            // should be validated in code which calls this queue
            $coupon = Coupon::active()->where('promotion_id', $couponId)->first();

            $message = 'Coupon name: ' . $coupon->promotion_name;
            $message = sprintf('[Job ID: `%s`] ISSUED COUPON MAIL QUEUE -- Status: OK -- Send email to: %s -- Coupon ID: %s -- Message: %s',
                                $job->getJobId(), $user->user_email, $couponId, $message);

            $this->sendCouponEmail(['user' => $user, 'coupon' => $coupon, 'redeem_url' => $data['redeem_url']]);

            $job->delete();

            Log::info($message);

            return ['status' => 'ok', 'message' => $message];
        } catch (ModelNotFoundException $e) {
            $message = sprintf('[Job ID: `%s`] ISSUED COUPON MAIL QUEUE -- Status: FAIL -- Send email to: %s -- Coupon ID: %s -- Message: %s -- File: %s -- Line: %s',
                                $job->getJobId(), $email, $couponId,
                                $e->getMessage(), $e->getFile(), $e->getLine());
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] ISSUED COUPON MAIL QUEUE -- Status: FAIL -- Send email to: %s -- Coupon ID: %s -- Message: %s -- File: %s -- Line: %s',
                                $job->getJobId(), $email, $couponId,
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
     * Sending email to user which contains coupon information.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param array $data
     * @return void
     */
    protected function sendCouponEmail($data)
    {
        $emailData = [
            'email' => $data['user']->user_email,
            'coupon_name' => $data['coupon']->promotion_name,
            'coupon_expired' => $data['coupon']->coupon_validity_in_date,
            'base_url' => rtrim(Config::get('app.url'), '/'),
            'redeem_url' => $data['redeem_url']
        ];
        $mailviews = [
            'html' => 'emails.coupon.issued-coupon-html',
            'text' => 'emails.coupon.issued-coupon-text'
        ];

        Mail::send($mailviews, $emailData, function($message) use ($data)
        {
            $emailconf = Config::get('orbit.registration.mobile.sender');
            $from = $emailconf['email'];
            $name = $emailconf['name'];

            $subject = 'Anda mendapatkan kupon: ' . $data['coupon']->promotion_name;
            $message->from($from, $name)->subject($subject);
            $message->to($data['user']->user_email);
        });
    }
}