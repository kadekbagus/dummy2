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
use Coupon;

use Orbit\Helper\Sepulsa\API\TakeVoucher;
use Orbit\Helper\Sepulsa\API\Responses\TakeVoucherResponse;

use Orbit\Notifications\Coupon\Sepulsa\ReceiptNotification as SepulsaReceiptNotification;
use Orbit\Notifications\Coupon\Sepulsa\TakeVoucherFailureNotification;
use Orbit\Notifications\Coupon\Sepulsa\VoucherNotAvailableNotification;

use Orbit\Notifications\Coupon\CouponNotAvailableNotification;

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
     * @todo  move retry routine to a unified method.
     *
     * @param  [type] $job  [description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function fire($job, $data)
    {
        $notificationDelay = 1;

        try {

            DB::beginTransaction();

            $paymentId = $data['paymentId'];

            Log::info("PaidCoupon: Getting Sepulsa Voucher for paymentID: {$paymentId}");

            $payment = PaymentTransaction::onWriteConnection()->with(['coupon', 'coupon_sepulsa', 'issued_coupon', 'user'])->find($paymentId);

            \Log::info('GETCOUPON payment: ' . serialize($payment));

            if (empty($payment)) {
                throw new Exception("Transaction {$paymentId} not found!");
            }

            // Dont issue coupon if after some delay the payment was canceled.
            if ($payment->denied() || $payment->failed() || $payment->expired()) {

                Log::info('PaidCoupon: Payment ' . $paymentId . ' was denied/canceled.');

                $payment->cleanUp();

                DB::commit();

                $job->delete();

                return;
            }

            // It means we can not get related issued coupon.
            if (empty($payment->issued_coupon)) {

                $payment->cleanUp();

                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED;
                $payment->save();

                DB::commit();

                $this->notifyFailedCoupon($payment, 'Related IssuedCoupon not found. Might be put to stock again by system queue before customer complete the payment.');

                $job->delete();

                return;
            }

            // If coupon already issued...
            if ($payment->issued_coupon->status === IssuedCoupon::STATUS_ISSUED) {
                Log::info('PaidCoupon: Coupon already issued. Nothing to do.');

                DB::commit();

                $job->delete();

                return;
            }

            $voucherToken = $payment->coupon_sepulsa->token;

            $takenVouchers = TakeVoucher::create()->take($paymentId, [['token' => $voucherToken]]);
            $takenVouchers = new TakeVoucherResponse($takenVouchers);

            if ($takenVouchers->isValid() && $takenVouchers->isSuccess()) {

                $takenVoucherData = $takenVouchers->getVoucherData();

                $issuedCoupon = IssuedCoupon::onWriteConnection()->where('transaction_id', $paymentId)->first();

                \Log::info('GETCOUPON IssuedCoupon: ' . serialize($issuedCoupon));

                $coupon = Coupon::onWriteConnection()->find($payment->object_id);

                \Log::info('GETCOUPON Coupon: ' . serialize($coupon));

                // Update related issued coupon based on data we get from Sepulsa.
                $issuedCoupon->redeem_verification_code       = $takenVoucherData->id;
                $issuedCoupon->issued_coupon_code = $takenVoucherData->code;
                $issuedCoupon->url                = $takenVoucherData->redeem_url;
                $issuedCoupon->issued_date        = Carbon::now();
                $issuedCoupon->expired_date       = $coupon->coupon_validity_in_date;
                $issuedCoupon->status             = IssuedCoupon::STATUS_ISSUED;

                $issuedCoupon->save();

                \Log::info('GETCOUPON IssuedCoupon After save(): ' . serialize($issuedCoupon));

                // Update payment transaction data
                $payment->coupon_redemption_code = $takenVoucherData->code;
                $payment->status = PaymentTransaction::STATUS_SUCCESS;
                $payment->save();

                $coupon->updateAvailability();

                // Commit ASAP.
                DB::commit();

                \Log::info('GETCOUPON IssuedCoupon After commit(): ' . serialize($issuedCoupon));

                // Notify customer for receipt/inApp.
                $payment->user->notify(new SepulsaReceiptNotification($payment), $notificationDelay);

                Log::info('PaidCoupon: Coupon issued for paymentID: ' . $paymentId);
            }
            else {
                // This means the TakeVoucher request failed.
                $payment->notes = $payment->notes . $takenVouchers->getMessage() . "\n------\n";

                $this->retryJob($data, $payment, $takenVouchers, null);
            }

        } catch (Exception $e) {

            // Failed to get token...
            if ($e->getCode() === 501) {
                Log::info('PaidCoupon: Failed to get token.');
                $this->retryJob($data, $payment, null, $e);
            }
            else {
                // Assume unhandled exception or payment not found.
                if (! isset($payment)) {
                    $payment = null;
                }
                if (! isset($takenVouchers)) {
                    $takenVouchers = null;
                }

                $this->retryJob($data, $payment, $takenVouchers, $e);
            }
        }

        $issuedCoupon2 = IssuedCoupon::onWriteConnection()->where('transaction_id', $paymentId)->first();

        \Log::info('GETCOUPON IssuedCoupon After commit() fresh: ' . serialize($issuedCoupon2));

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
    private function jobShouldRetry($retries = 1, TakeVoucherResponse $takenVouchers = null)
    {
        $maxRetry = Config::get('orbit.partners_api.sepulsa.take_voucher_max_retry', 3);

        if (! empty($takenVouchers)) {
            return $retries < $maxRetry && ! $takenVouchers->isExpired();
        }

        return $retries < $maxRetry;
    }

    /**
     * Retry this job if we need.
     *
     * @param  [type] $data          [description]
     * @param  [type] $payment       [description]
     * @param  [type] $takenVouchers [description]
     * @param  [type] $e             [description]
     * @return [type]                [description]
     */
    private function retryJob($data, $payment = null, $takenVouchers = null, $e = null)
    {
        if (! empty($payment)) {

            $failureMessage = ! empty($takenVouchers) ?  $takenVouchers->getMessage() : $e->getMessage();

            // Let's retry TakeVoucher request...
            if ($this->jobShouldRetry($data['retries'], $takenVouchers)) {

                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON;
                $payment->save();

                DB::commit();

                $delay = Config::get('orbit.partners_api.sepulsa.take_voucher_retry_timeout', 30);
                $data['retries']++;

                // Retry this job by re-pushing it to Queue.
                Queue::later(
                    $delay,
                    'Orbit\\Queue\\Coupon\\Sepulsa\\GetCouponQueue',
                    $data
                );

                Log::info(sprintf(
                    'PaidCoupon: TakeVoucher Request: Retrying in %s seconds... Status: FAILED, CouponID: %s --- Message: %s',
                    $delay,
                    $payment->object_id,
                    $failureMessage
                ));
            }
            else {
                // Oh, no more retry, huh?
                // We should set new status for the payment to indicate success payment but no coupon after trying for a few times.
                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED;
                $payment->save();

                // Clean up payment since we can not issue the coupon.
                $payment->cleanUp();

                DB::commit();

                Log::info(sprintf(
                    'PaidCoupon: TakeVoucher Request: Maximum Retry reached... Status: FAILED, CouponID: %s --- Message: %s',
                    $payment->object_id,
                    $failureMessage
                ));

                $this->notifyFailedCoupon($payment, $failureMessage);
            }
        }
        else {
            Log::info(sprintf(
                'PaidCoupon: Can not get voucher, exception: %s:%s, %s',
                $e->getFile(),
                $e->getLine(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Notify admin and customer that we fail to issue the coupon.
     *
     * @param  [type]  $payment        [description]
     * @param  [type]  $failureMessage [description]
     * @param  integer $delay          [description]
     * @return [type]                  [description]
     */
    private function notifyFailedCoupon($payment, $failureMessage, $delay = 3)
    {
        $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);

        // Notify Admin that the voucher is failed and customer's money should be refunded.
        foreach($adminEmails as $email) {
            $devUser            = new User;
            $devUser->email     = $email;
            $devUser->notify(new CouponNotAvailableNotification($payment, $failureMessage), $delay);
        }

        // Notify customer that the coupon is not available and the money will be refunded.
        $payment->user->notify(new VoucherNotAvailableNotification($payment));
    }

}
