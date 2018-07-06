<?php namespace Orbit\Queue\Coupon\HotDeals;

use DB;
use Log;
use Event;
use Queue;
use Config;
use Exception;
use Carbon\Carbon;
use Orbit\FakeJob;
use Orbit\Helper\Util\JobBurier;

use User;
use PaymentTransaction;
use IssuedCoupon;

// Notifications
use Orbit\Notifications\Coupon\CouponNotAvailableNotification;
use Orbit\Notifications\Coupon\HotDeals\ReceiptNotification as HotDealsReceiptNotification;
use Orbit\Notifications\Coupon\HotDeals\CouponNotAvailableNotification as HotDealsCouponNotAvailableNotification;


/**
 * A job to get/issue Hot Deals Coupon after payment completed.
 *
 * @author Budi <budi@dominopos.com>
 */
class GetCouponQueue
{
    /**
     * Issue hot deals coupon.
     * 
     * @param  [type] $job  [description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function fire($job, $data)
    {
        $notificationDelay = 5;

        // TODO: Move to config?
        $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);

        try {
            DB::connection()->beginTransaction();

            $paymentId = $data['paymentId'];
            $retries = $data['retries'];

            Log::info(sprintf('PaidCoupon: Getting coupon PaymentID: %s', $paymentId));

            $payment = PaymentTransaction::with(['issued_coupon', 'user'])->findOrFail($paymentId);

            // Dont issue coupon if after some delay the payment was canceled.
            if ($payment->denied() || $payment->failed() || $payment->expired()) {
                
                Log::info('PaidCoupon: Payment ' . $paymentId . ' was denied/canceled. We should not issuing any coupon.');
                
                $payment->cleanUp();

                DB::connection()->commit();

                return;
            }

            // It means we can not get related issued coupon.
            if (empty($payment->issued_coupon)) {

                Log::info('PaidCoupon: Can not get related IssuedCoupon for payment ' . $paymentId);

                $payment->cleanUp();

                DB::connection()->commit();

                // Notify admin for this failure.
                foreach($adminEmails as $email) {
                    $admin              = new User;
                    $admin->email       = $email;
                    $admin->notify(new CouponNotAvailableNotification($payment, 'Related IssuedCoupon not found.'), 3);
                }

                // Notify customer that coupon is not available.
                $payment->user->notify(new HotDealsCouponNotAvailableNotification($payment), 3);

                return;
            }

            // Issue the coupon...
            $payment->issued_coupon->issued_date = Carbon::now('UTC');
            $payment->issued_coupon->status      = IssuedCoupon::STATUS_ISSUED;

            $payment->issued_coupon->save();

            $payment->coupon_redemption_code = $payment->issued_coupon->issued_coupon_code;
            $payment->status = PaymentTransaction::STATUS_SUCCESS;
            $payment->save();

            // Commit the changes ASAP.
            DB::connection()->commit();

            Log::info('PaidCoupon: Coupon issued..');

            // Notify Customer.
            $payment->user->notify(new HotDealsReceiptNotification($payment), $notificationDelay);

        } catch (Exception $e) {
            DB::connection()->rollback();
            Log::info(sprintf('PaidCoupon: Get HotDeals Coupon exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
            Log::info('PaidCoupon: Data: ' . serialize($data));
        }
    }
}
