<?php namespace Orbit\Queue\Coupon\HotDeals;

use DB;
use Log;
use Event;
use Queue;
use Config;
use Exception;
use Carbon\Carbon;

use User;
use PaymentTransaction;
use IssuedCoupon;
use Coupon;

// Notifications
use Orbit\Notifications\Coupon\CouponNotAvailableNotification;
use Orbit\Notifications\Coupon\HotDeals\ReceiptNotification as HotDealsReceiptNotification;
use Orbit\Notifications\Coupon\HotDeals\CouponNotAvailableNotification as HotDealsCouponNotAvailableNotification;

/**
 * A job to get/issue Hot Deals Coupon after payment completed.
 * At this point, we assume the payment was completed (paid) so anything wrong 
 * while trying to issue the coupon will make the status success_no_coupon_failed.
 *
 * @author Budi <budi@dominopos.com>
 */
class GetCouponQueue
{
    /**
     * Issue hot deals coupon.
     *
     * @param  Illuminate\Queue\Jobs\Job | Orbit\FakeJob $job  the job
     * @param  array $data the data needed to run this job
     * @return void
     */
    public function fire($job, $data)
    {
        $notificationDelay = 5;

        $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);

        try {
            DB::connection()->beginTransaction();

            $paymentId = $data['paymentId'];

            Log::info(sprintf('PaidCoupon: Getting coupon PaymentID: %s', $paymentId));

            $payment = PaymentTransaction::with(['details.coupon', 'issued_coupon.coupon', 'user'])->findOrFail($paymentId);

            // Dont issue coupon if after some delay the payment was canceled.
            if ($payment->denied() || $payment->failed() || $payment->expired()) {

                Log::info('PaidCoupon: Payment ' . $paymentId . ' was denied/canceled. We should not issue any coupon.');

                $payment->cleanUp();

                DB::connection()->commit();

                return;
            }

            // It means we can not get related issued coupon.
            if (empty($payment->issued_coupon)) {
                $payment->cleanUp();
                throw new Exception("Related IssuedCoupon not found. Might be put to stock again by system queue before customer complete the payment.", 1);
            }

            // If coupon already issued/redeemed...
            if (in_array($payment->issued_coupon->status, [IssuedCoupon::STATUS_ISSUED, IssuedCoupon::STATUS_REDEEMED])) {
                Log::info('PaidCoupon: Coupon already issued/redeemed. Nothing to do.');
                DB::connection()->rollback();
            }
            else {
                // Issue the coupon...
                $payment->issued_coupon->issued_date = Carbon::now('UTC');
                $payment->issued_coupon->status      = IssuedCoupon::STATUS_ISSUED;

                $payment->issued_coupon->save();

                $payment->coupon_redemption_code = $payment->issued_coupon->issued_coupon_code;
                $payment->status = PaymentTransaction::STATUS_SUCCESS;
                $payment->save();

                // $availableCoupon = Coupon::where('promotion_id', $payment->issued_coupon->promotion_id)->first();
                if (! empty($payment->issued_coupon->coupon)) {
                    $payment->issued_coupon->coupon->updateAvailability();
                }

                // Commit the changes ASAP.
                DB::connection()->commit();

                Log::info('PaidCoupon: Coupon issued..');

                // Notify Customer.
                $payment->user->notify(new HotDealsReceiptNotification($payment), $notificationDelay);
            }

        } catch (Exception $e) {
            
            // Mark as failed if we get any exception.
            if (! empty($payment)) {
                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED;
                $payment->save();

                DB::connection()->commit();

                // Notify admin for this failure.
                foreach($adminEmails as $email) {
                    $admin              = new User;
                    $admin->email       = $email;
                    $admin->notify(new CouponNotAvailableNotification($payment, $e->getMessage()), 3);
                }

                // Notify customer that coupon is not available.
                $payment->user->notify(new HotDealsCouponNotAvailableNotification($payment), 3);
            }
            else {
                DB::connection()->rollback();
            }

            Log::info(sprintf('PaidCoupon: Get HotDeals Coupon exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
            Log::info('PaidCoupon: Data: ' . serialize($data));
        }

        $job->delete();
    }

}
