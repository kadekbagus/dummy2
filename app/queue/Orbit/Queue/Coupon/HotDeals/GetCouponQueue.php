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

use PaymentTransaction;

use Orbit\Helper\Sepulsa\API\TakeVoucher;
use Orbit\Helper\Sepulsa\API\Responses\TakeVoucherResponse;

// Notifications
use Orbit\Notifications\Coupon\HotDeals\ReceiptNotification as HotDealsReceiptNotification;
use Orbit\Notifications\Coupon\HotDeals\CouponNotAvailableNotification as HotDealsCouponNotAvailableNotification;

use Orbit\Notifications\Coupon\CouponNotAvailableNotification;
use Orbit\Notifications\Coupon\CustomerCouponNotAvailableNotification;

/**
 * A job to get/issue Hot Deals Coupon after payment completed.
 *
 * @author Budi <budi@dominopos.com>
 */
class GetCouponQueue
{
    /**
     * 
     * 
     * @param  [type] $job  [description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function fire($job, $data)
    {
        $notificationDelay = 5;

        // TODO: Move to config?
        // $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);

        try {
            DB::connection()->beginTransaction();

            $paymentId = $data['paymentId'];
            $retries = $data['retries'];

            $payment = PaymentTransaction::with(['coupon', 'issued_coupon', 'user'])->findOrFail($paymentId);

            Log::info(sprintf('PaidCoupon: Getting coupon %s for PaymentID: %s', $payment->object_id, $paymentId));

            // Claim the coupon...
            $payment->issued_coupon->transaction_id     = $paymentId;
            $payment->issued_coupon->issued_date        = Carbon::now('UTC');

            // Coupon already issued...
            $payment->issued_coupon->save();

            $payment->coupon_redemption_code = $payment->issued_coupon->issued_coupon_code;
            $payment->status = PaymentTransaction::STATUS_SUCCESS;
            $payment->save();

            $payment->coupon->updateAvailability();

            DB::connection()->commit();

            // Notify Customer.
            $payment->user->notify(new HotDealsReceiptNotification($payment), $notificationDelay);

        } catch (Exception $e) {
            DB::connection()->rollback();
            Log::info(sprintf('PaidCoupon: Get Coupon exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
            Log::info('PaidCoupon: Data: ' . serialize($data));
        }
    }
}
