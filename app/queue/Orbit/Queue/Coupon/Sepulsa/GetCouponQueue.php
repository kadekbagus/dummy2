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

use PaymentTransaction;

use Orbit\Helper\Sepulsa\API\TakeVoucher;
use Orbit\Helper\Sepulsa\API\Responses\TakeVoucherResponse;

use Orbit\Notifications\Coupon\Sepulsa\ReceiptNotification as SepulsaReceiptNotification;
use Orbit\Notifications\Coupon\Sepulsa\TakeVoucherFailureNotification;
use Orbit\Notifications\Coupon\Sepulsa\VoucherNotAvailableNotification as SepulsaVoucherNotAvailableNotification;

use Orbit\Notifications\Coupon\CouponNotAvailableNotification;
use Orbit\Notifications\Coupon\CustomerCouponNotAvailableNotification;

/**
 * A job to issue Sepulsa Voucher after payment completed.
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
        $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);

        try {

            DB::connection()->beginTransaction();

            $paymentId = $data['paymentId'];
            $retries = $data['retries'];

            Log::info("PaidCoupon: Getting Sepulsa Voucher for paymentID: {$paymentId}");

            $payment = PaymentTransaction::with(['coupon', 'coupon_sepulsa', 'issued_coupon', 'user'])->findOrFail($paymentId);
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

                $payment->issued_coupon->save();

                // Update payment transaction data
                $payment->coupon_redemption_code = $takenVoucherData->code;
                $payment->status = PaymentTransaction::STATUS_SUCCESS;
                $payment->save();

                // Commit ASAP.
                DB::connection()->commit();

                // Notify customer for receipt/inApp.
                $payment->user->notify(new SepulsaReceiptNotification($payment), $notificationDelay);

                return;
            }
            else {
                // This means the TakeVoucher request failed.
                // We need to record the failure...
                $payment->notes = $payment->notes . $takenVouchers->getMessage() . "\n------\n";
                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON;

                $payment->save();

                // If this is the first failure, then we should notify developer via email.
                if ($retries === 0) {
                    $devUser            = new User;
                    $devUser->email     = Config::get('orbit.contact_information.developer.email', 'developer@dominopos.com');
                    $devUser->notify(new TakeVoucherFailureNotification($payment, $takenVouchers, $retries), $notificationDelay);

                    $errorMessage = sprintf('PaidCoupon: TakeVoucher Request: First try failed. Status: FAILED, CouponID: %s --- Message: %s', $payment->object_id, $takenVouchers->getMessage());
                    Log::info($errorMessage);
                }

                // Let's retry TakeVoucher request...
                $maxRetry = Config::get('orbit.partners_api.sepulsa.take_voucher_max_retry', 3);
                if ($retries < $maxRetry) {
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

                    // Remove temporary created IssuedCoupon, since we can not get the voucher from Sepulsa.
                    IssuedCoupon::where('transaction_id', $paymentId)->delete();

                    $payment->coupon->available = $payment->coupon->available + 1;
                    $payment->coupon->save();
                    
                    DB::connection()->commit();

                    $errorMessage = sprintf('PaidCoupon: TakeVoucher Request: Maximum Retry reached... Status: FAILED, CouponID: %s --- Message: %s', $payment->object_id, $takenVouchers->getMessage());

                    Log::info($errorMessage);

                    // Notify Admin that the voucher is failed and customer's money should be refunded.
                    foreach($adminEmails as $email) {
                        $devUser            = new User;
                        $devUser->email     = $email;
                        $devUser->notify(new TakeVoucherFailureNotification($payment, $takenVouchers, $retries), $notificationDelay);
                    }

                    // Notify customer that the coupon is not available and the money will be refunded.
                    $payment->user->notify(new SepulsaVoucherNotAvailableNotification($payment), $notificationDelay);

                    return;
                }

                Log::info($errorMessage);
            }

            DB::connection()->commit();

        } catch (Exception $e) {
            DB::connection()->rollback();
            Log::info(sprintf('PaidCoupon: Can not get voucher, exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
            Log::info('PaidCoupon: data: ' . serialize($data));
        }
    }
}
