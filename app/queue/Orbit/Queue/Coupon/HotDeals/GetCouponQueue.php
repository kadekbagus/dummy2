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
use Mall;
use Activity;

// Notifications
use Orbit\Notifications\Coupon\CouponNotAvailableNotification;
use Orbit\Notifications\Coupon\HotDeals\ReceiptNotification;
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
        $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);
        $mallId = isset($data['mall_id']) ? $data['mall_id'] : null;
        $mall = Mall::where('merchant_id', $mallId)->first();

        $activity = Activity::mobileci()
                            ->setActivityType('transaction')
                            ->setActivityName('transaction_status');

        try {
            DB::connection()->beginTransaction();

            $paymentId = $data['paymentId'];

            Log::info("PaidCoupon: Getting coupon PaymentID: {$paymentId}");

            $payment = PaymentTransaction::with(['details.coupon', 'user', 'midtrans', 'issued_coupons'])->findOrFail($paymentId);

            $activity->setUser($payment->user);

            // Dont issue coupon if after some delay the payment was canceled.
            if ($payment->denied() || $payment->failed() || $payment->expired()) {

                Log::info("PaidCoupon: Payment {$paymentId} was denied/canceled. We should not issue any coupon.");

                $payment->cleanUp();

                DB::connection()->commit();

                $job->delete();

                return;
            }

            // It means we can not get related issued coupon.
            if ($payment->issued_coupons->count() === 0) {
                throw new Exception("Related IssuedCoupon not found. Might be put to stock again by system queue before customer completes the payment.", 1);
            }

            // If coupon already issued/redeemed...
            $issuedCouponCount = 0;
            foreach($payment->issued_coupons as $issuedCoupon) {
                if ($issuedCoupon->status === IssuedCoupon::STATUS_RESERVED) {
                    // Issue the coupon...
                    $issuedCoupon->issued_date = Carbon::now('UTC');
                    $issuedCoupon->status      = IssuedCoupon::STATUS_ISSUED;

                    $issuedCoupon->save();

                    $issuedCouponCount++;

                    Log::info("PaidCoupon: IssuedCoupon {$issuedCoupon->issued_coupon_id} issued for payment {$paymentId}... ({$issuedCouponCount})");
                }
                else {
                    Log::info("PaidCoupon: IssuedCoupon {$issuedCoupon->issued_coupon_id} status is {$issuedCoupon->status}. Nothing to do.");
                }
            }

            if ($issuedCouponCount > 0) {
                $payment->status = PaymentTransaction::STATUS_SUCCESS;
                $payment->save();

                $coupon = $payment->details->first()->coupon;
                $coupon->updateAvailability();

                // Commit the changes ASAP.
                DB::connection()->commit();

                Log::info("PaidCoupon: {$issuedCouponCount} coupon issued for payment {$paymentId}..");

                // Notify Customer.
                $payment->user->notify(new ReceiptNotification($payment));

                // Log Activity
                $activity->setActivityNameLong('Transaction is Successful')
                        ->setModuleName('Midtrans Transaction')
                        ->setObject($payment)
                        ->setCoupon($coupon)
                        ->setNotes(Coupon::TYPE_HOT_DEALS)
                        ->setLocation($mall)
                        ->responseOK()
                        ->save();

                Activity::mobileci()
                        ->setUser($payment->user)
                        ->setActivityType('click')
                        ->setActivityName('coupon_added_to_wallet')
                        ->setActivityNameLong('Coupon Added To Wallet')
                        ->setModuleName('Coupon')
                        ->setObject($coupon)
                        ->setNotes(Coupon::TYPE_HOT_DEALS)
                        ->setLocation($mall)
                        ->responseOK()
                        ->save();

            }
            else {
                // Commit the changes ASAP.
                DB::connection()->rollBack();

                Log::info("PaidCoupon: NO COUPON ISSUED for payment {$paymentId} !");
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
                    $admin->notify(new CouponNotAvailableNotification($payment, $e->getMessage()));
                }

                // Notify customer that coupon is not available.
                $payment->user->notify(new HotDealsCouponNotAvailableNotification($payment));

                $activity->setActivityNameLong('Transaction is Success - Failed Getting Coupon')
                         ->setModuleName('Midtrans Transaction')
                         ->setObject($payment)
                         ->setNotes($e->getMessage())
                         ->setLocation($mall)
                         ->responseFailed()
                         ->save();

                 Activity::mobileci()
                         ->setUser($payment->user)
                         ->setActivityType('click')
                         ->setActivityName('coupon_added_to_wallet')
                         ->setActivityNameLong('Coupon Added to Wallet Failed')
                         ->setModuleName('Coupon')
                         ->setObject($payment->details->first()->coupon)
                         ->setNotes($e->getMessage())
                         ->setLocation($mall)
                         ->responseFailed()
                         ->save();
            }
            else {
                DB::connection()->rollBack();
            }

            Log::info(sprintf('PaidCoupon: Get HotDeals Coupon exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
            Log::info('PaidCoupon: Data: ' . serialize($data));
        }

        $job->delete();
    }
}
