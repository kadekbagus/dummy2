<?php namespace Orbit\Queue\Coupon\Sepulsa;

use DB;
use Log;
use Event;
use Queue;
use Config;
use Exception;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

use User;
use PaymentTransaction;
use IssuedCoupon;
use Coupon;
use Mall;
use Activity;
use TmpPromoCode;

use Orbit\Helper\Sepulsa\API\TakeVoucher;
use Orbit\Helper\Sepulsa\API\Responses\TakeVoucherResponse;

use Orbit\Notifications\Coupon\Sepulsa\ReceiptNotification;
use Orbit\Notifications\Coupon\Sepulsa\TakeVoucherFailureNotification;
use Orbit\Notifications\Coupon\Sepulsa\CouponNotAvailableNotification as SepulsaCouponNotAvailableNotification;

use Orbit\Notifications\Coupon\CouponNotAvailableNotification;

/**
 * A job to issue Sepulsa Voucher after payment completed.
 * At this point, we assume the payment was completed (paid) so anything wrong
 * while trying to issue the coupon will make the status success_no_coupon_failed.
 *
 * @author Budi <budi@dominopos.com>
 */
class GetCouponQueue
{
    protected $activity = null;

    protected $mall = '';

    /**
     * Get Sepulsa Voucher after payment completed.
     *
     * @todo  remove logging.
     * @todo  validate available issued coupons before taking/getting them from Sepulsa.
     * @todo  what happens if one of the voucher failed?
     *
     * @param  Illuminate\Queue\Jobs\Job | Orbit\FakeJob $job  the job
     * @param  array $data the data needed to run this job
     * @return void
     */
    public function fire($job, $data)
    {
        $mallId = isset($data['mall_id']) ? $data['mall_id'] : null;
        $this->mall = Mall::where('merchant_id', $mallId)->first();

        $this->activity = Activity::mobileci()
                            ->setActivityType('transaction')
                            ->setActivityName('transaction_status');

        try {

            DB::connection()->beginTransaction();

            $paymentId = $data['paymentId'];

            Log::info("PaidCoupon: Getting Sepulsa Voucher for payment {$paymentId}");

            $payment = PaymentTransaction::with(['details.coupon', 'user', 'midtrans', 'issued_coupons'])->findOrFail($paymentId);

            $this->activity->setUser($payment->user);

            // Dont issue coupon if after some delay the payment was canceled.
            if ($payment->denied() || $payment->failed() || $payment->expired()) {

                Log::info('PaidCoupon: Payment ' . $paymentId . ' was denied/canceled.');

                $payment->cleanUp();

                DB::connection()->commit();

                $job->delete();

                return;
            }

            // It means we can not get related issued coupon.
            if ($payment->issued_coupons->count() === 0) {

                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED;
                $payment->save();

                DB::connection()->commit();

                $this->notifyFailedCoupon($payment, 'Related IssuedCoupon not found. Might be put to stock again by system queue before customer complete the payment.');

                $job->delete();

                return;
            }

            // Manual query Coupon.
            $coupon = Coupon::with(['coupon_sepulsa'])->findOrFail($payment->details->first()->object_id);
            $voucherToken = $coupon->coupon_sepulsa->token;
            $reservedCoupons = [];
            $reservedCouponsToken = [];

            // Filter only reserved IssuedCoupon.
            // @todo may add relation reserved_coupons in payment model.
            foreach($payment->issued_coupons as $issuedCoupon) {
                if ($issuedCoupon->status === IssuedCoupon::STATUS_RESERVED) {
                    $reservedCoupons[] = $issuedCoupon->issued_coupon_id;
                    $reservedCouponsToken[] = ['token' => $voucherToken];
                }
                else {
                    Log::info("PaidCoupon: Coupon {$issuedCoupon->issued_coupon_id} status is {$issuedCoupon->status} for payment {$paymentId}. Nothing to do.");
                }
            }

            // If we have reserved IssuedCoupon, then try to issue them...
            $shouldIssueCouponCount = count($reservedCoupons);
            if ($shouldIssueCouponCount > 0) {
                Log::info("PaidCoupon: Issuing {$shouldIssueCouponCount} voucher from Sepulsa...");

                $takenVouchers = TakeVoucher::create()->take($paymentId, $reservedCouponsToken);
                $takenVouchers = new TakeVoucherResponse($takenVouchers);

                if ($takenVouchers->isValid() && $takenVouchers->isSuccess()) {
                    Log::info("PaidCoupon: Issued $shouldIssueCouponCount coupon from Sepulsa... ");

                    $takenVoucherData = $takenVouchers->getVoucherData();

                    foreach($takenVoucherData as $index => $voucherData) {

                        // Only update internal IssuedCoupon record if it
                        if (isset($reservedCoupons[$index])) {
                            // Manual query IssuedCoupon
                            $issuedCoupon = IssuedCoupon::find($reservedCoupons[$index]);

                            $issuedCoupon->redeem_verification_code = $voucherData->id;
                            $issuedCoupon->issued_coupon_code       = $voucherData->code;
                            $issuedCoupon->url                      = $voucherData->redeem_url;
                            $issuedCoupon->issued_date              = Carbon::now();
                            $issuedCoupon->expired_date             = $voucherData->expired_date;
                            $issuedCoupon->status                   = IssuedCoupon::STATUS_ISSUED;

                            $issuedCoupon->save();
                        }
                    }

                    if (isset($data['discountCode']) &&
                        isset($data['issuedCouponId']) &&
                        isset($data['couponId']) &&
                        isset($data['userId'])) {
                        $newPromoCode = new TmpPromoCode();
                        $newPromoCode->promo_code = $data['discountCode'];
                        $newPromoCode->coupon_id = $data['couponId'];
                        $newPromoCode->user_id = $data['userId'];
                        $newPromoCode->issued_coupon_id = $data['issuedCouponId'];
                        $newPromoCode->save();
                    }

                    // Update payment transaction data
                    $payment->status = PaymentTransaction::STATUS_SUCCESS;
                    $payment->save();

                    $coupon->updateAvailability();

                    // Commit ASAP.
                    DB::connection()->commit();

                    // Notify customer for receipt/inApp.
                    $payment->user->notify(new ReceiptNotification($payment));

                    // Log Activity
                    $this->activity->setActivityNameLong('Transaction is Successful')
                            ->setModuleName('Midtrans Transaction')
                            ->setObject($payment)
                            ->setCoupon($coupon)
                            ->setNotes(Coupon::TYPE_SEPULSA)
                            ->setLocation($this->mall)
                            ->responseOK()
                            ->save();

                    Activity::mobileci()
                            ->setUser($payment->user)
                            ->setActivityType('click')
                            ->setActivityName('coupon_added_to_wallet')
                            ->setActivityNameLong('Coupon Added To Wallet')
                            ->setModuleName('Coupon')
                            ->setObject($coupon)
                            ->setNotes(Coupon::TYPE_SEPULSA)
                            ->setLocation($this->mall)
                            ->responseOK()
                            ->save();
                }
                else {
                    Log::info("PaidCoupon: Failed to issue coupon from Sepulsa!");
                    // This means the TakeVoucher request failed, and we should retry (or not?)
                    $payment->notes = $payment->notes . $takenVouchers->getMessage() . "\n------\n";

                    $this->retryJob($data, $payment, $takenVouchers, null);
                }
            }
            else {
                Log::info("PaidCoupon: All coupons issued/redeemed for payment {$paymentId}.");
            }
        } catch (QueryException $e) {
            \Log::info('GETCOUPON QUERY EXCEPTION: ' . serialize([$e->getMessage(), $e->getFile(), $e->getLine()]));
        } catch (Exception $e) {

            // Failed to get token...
            if ($e->getCode() === 501) {
                Log::info('PaidCoupon: Failed to get token.');
                $this->retryJob($data, $payment, null, $e);
            }
            else {
                \Log::info('GETCOUPON EXCEPTION: ' . serialize([$e->getMessage(), $e->getFile(), $e->getLine()]));
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

        $issuedCoupon2 = IssuedCoupon::where('transaction_id', $paymentId)->first();

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

                DB::connection()->commit();

                $delay = Config::get('orbit.partners_api.sepulsa.take_voucher_retry_timeout', 30);
                $data['retries']++;

                // Retry this job by re-pushing it to Queue.
                Queue::connection('sync')->later(
                    $delay,
                    'Orbit\\Queue\\Coupon\\Sepulsa\\GetCouponQueue',
                    $data
                );

                Log::info(sprintf(
                    'PaidCoupon: Take Voucher Retrying in %s seconds... Status: FAILED, CouponID: %s --- Message: %s',
                    $delay,
                    $payment->details->first()->object_id,
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

                DB::connection()->commit();

                Log::info(sprintf(
                    'PaidCoupon: TakeVoucher Maximum Retry reached... Status: FAILED, CouponID: %s --- Message: %s',
                    $payment->details->first()->object_id,
                    $failureMessage
                ));

                $this->notifyFailedCoupon($payment, $failureMessage);

                $this->activity->setActivityNameLong('Transaction is Success - Failed Getting Coupon')
                        ->setModuleName('Midtrans Transaction')
                        ->setObject($payment)
                        ->setNotes($failureMessage)
                        ->setLocation($this->mall)
                        ->responseFailed()
                        ->save();

                Activity::mobileci()
                        ->setUser($payment->user)
                        ->setActivityType('click')
                        ->setActivityName('coupon_added_to_wallet')
                        ->setActivityNameLong('Coupon Added to Wallet Failed')
                        ->setModuleName('Coupon')
                        ->setObject($payment->details->first()->coupon)
                        ->setNotes($failureMessage)
                        ->setLocation($this->mall)
                        ->responseFailed()
                        ->save();
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
            $devUser->notify(new CouponNotAvailableNotification($payment, $failureMessage));
        }

        // Notify customer that the coupon is not available and the money will be refunded.
        $payment->user->notify(new SepulsaCouponNotAvailableNotification($payment));
    }

}
