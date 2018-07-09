<?php namespace Orbit\Queue\Coupon\Sepulsa;

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

use Orbit\Helper\Sepulsa\API\TakeVoucher;
use Orbit\Helper\Sepulsa\API\Responses\TakeVoucherResponse;

use Orbit\Notifications\Coupon\Sepulsa\ReceiptNotification as SepulsaReceiptNotification;
use Orbit\Notifications\Coupon\Sepulsa\TakeVoucherFailureNotification;
use Orbit\Notifications\Coupon\Sepulsa\VoucherNotAvailableNotification as SepulsaVoucherNotAvailableNotification;

// use Orbit\Notifications\Coupon\CouponNotAvailableNotification;
// use Orbit\Notifications\Coupon\CustomerCouponNotAvailableNotification;

/**
 * A job to issue Sepulsa Voucher after payment completed.
 *
 * @author Budi <budi@dominopos.com>
 */
class GetCouponQueue
{
    /**
     * Get Sepulsa Voucher after payment completed.
     *
     * @todo  do we still need to send notification on the first failure?
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

            Log::info("PaidCoupon: Getting Sepulsa Voucher for paymentID: {$paymentId}");

            $payment = PaymentTransaction::with(['coupon', 'coupon_sepulsa', 'issued_coupon', 'user'])->findOrFail($paymentId);

            // Dont issue coupon if after some delay the payment was canceled.
            if ($payment->denied() || $payment->failed() || $payment->expired()) {
                
                Log::info('PaidCoupon: Payment ' . $paymentId . ' was denied/canceled.');
                
                $payment->cleanUp();

                DB::connection()->commit();

                $job->delete();

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

            // If coupon already issued...
            if ($payment->issued_coupon->status === IssuedCoupon::STATUS_ISSUED) {
                Log::info('PaidCoupon: Coupon already issued. Nothing to do.');
                return;
            }

            $voucherToken = $payment->coupon_sepulsa->token;

            $takenVouchers = TakeVoucher::create()->take($paymentId, [['token' => $voucherToken]]);
            $takenVouchers = new TakeVoucherResponse($takenVouchers);

            if ($takenVouchers->isValid() && $takenVouchers->isSuccess()) {

                $takenVoucherData = $takenVouchers->getVoucherData();

                // Update related issued coupon based on data we get from Sepulsa.
                $payment->issued_coupon->redeem_verification_code       = $takenVoucherData->id;
                $payment->issued_coupon->issued_coupon_code = $takenVoucherData->code;
                $payment->issued_coupon->url                = $takenVoucherData->redeem_url;
                $payment->issued_coupon->issued_date        = $takenVoucherData->taken_date;
                $payment->issued_coupon->expired_date       = $takenVoucherData->expired_date;
                $payment->issued_coupon->status             = IssuedCoupon::STATUS_ISSUED;

                $payment->issued_coupon->save();

                // Update payment transaction data
                $payment->coupon_redemption_code = $takenVoucherData->code;
                $payment->status = PaymentTransaction::STATUS_SUCCESS;
                $payment->save();

                // Commit ASAP.
                DB::connection()->commit();

                // Notify customer for receipt/inApp.
                $payment->user->notify(new SepulsaReceiptNotification($payment), $notificationDelay);

                Log::info('PaidCoupon: Coupon issued for paymentID: ' . $paymentId);

                $job->delete();

                return;
            }
            else {
                // This means the TakeVoucher request failed.
                $payment->notes = $payment->notes . $takenVouchers->getMessage() . "\n------\n";

                // Let's retry TakeVoucher request...
                if ($this->jobShouldRetry($retries, $takenVouchers)) {

                    $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON;
                    $payment->save();

                    DB::connection()->commit();

                    // If this is the first failure, then we should notify developer via email.
                    // NOTE do we still need this?
                    if ($retries === 0) {
                        $devUser            = new User;
                        $devUser->email     = Config::get('orbit.contact_information.developer.email', 'developer@dominopos.com');
                        $devUser->notify(new TakeVoucherFailureNotification($payment, $takenVouchers, $retries), $notificationDelay);

                        $errorMessage = sprintf('PaidCoupon: TakeVoucher Request: First try failed. Status: FAILED, CouponID: %s --- Message: %s', $payment->object_id, $takenVouchers->getMessage());
                        Log::info($errorMessage);
                    }

                    $delay = Config::get('orbit.partners_api.sepulsa.take_voucher_retry_timeout', 30);
                    $retries++;

                    // Retry this job by re-pushing it to Queue.
                    Queue::later(
                        $delay,
                        'Orbit\\Queue\\Coupon\\Sepulsa\\GetCouponQueue', 
                        compact('paymentId', 'retries')
                    );

                    $errorMessage = sprintf('PaidCoupon: TakeVoucher Request: Retrying in %s seconds... Status: FAILED, CouponID: %s --- Message: %s', $delay, $payment->object_id, $takenVouchers->getMessage());
                }
                else {
                    // Oh, no more retry, huh?

                    // We should set new status for the payment to indicate success payment but no coupon after trying for a few times.
                    $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED;
                    $payment->save();

                    // Clean up payment since we can not issue the coupon.
                    $payment->cleanUp();
                    
                    DB::connection()->commit();

                    if ($takenVouchers->isExpired()) {
                        $errorMessage = sprintf('PaidCoupon: Can not issue coupon, Sepulsa Voucher is EXPIRED. CouponID: %s, Voucher Token: %s', $payment->object_id, $voucherToken);
                    }
                    else {
                        $errorMessage = sprintf('PaidCoupon: TakeVoucher Request: Maximum Retry reached... Status: FAILED, CouponID: %s --- Message: %s', $payment->object_id, $takenVouchers->getMessage());
                    }

                    // Notify Admin that the voucher is failed and customer's money should be refunded.
                    foreach($adminEmails as $email) {
                        $devUser            = new User;
                        $devUser->email     = $email;
                        $devUser->notify(new TakeVoucherFailureNotification($payment, $takenVouchers, $retries), $notificationDelay);
                    }

                    // Notify customer that the coupon is not available and the money will be refunded.
                    $payment->user->notify(new SepulsaVoucherNotAvailableNotification($payment), $notificationDelay);
                }

                Log::info($errorMessage);
            }

        } catch (Exception $e) {
            DB::connection()->rollback();
            Log::info(sprintf('PaidCoupon: Can not get voucher, exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
            Log::info('PaidCoupon: data: ' . serialize($data));
        }

        $job->delete();
    }

    /**
     * Determine if we should retry the TakeVoucher request or not.
     * It should check the maximum allowed retry and the voucher expiration status.
     * 
     * @param  integer             $retries       [description]
     * @param  TakeVoucherResponse $takenVouchers [description]
     * @return [type]                             [description]
     */
    private function jobShouldRetry($retries = 1, TakeVoucherResponse $takenVouchers)
    {
        $maxRetry = Config::get('orbit.partners_api.sepulsa.take_voucher_max_retry', 3);

        return $retries < $maxRetry && ! $takenVouchers->isExpired();
    }
}
