<?php

use Orbit\Queue\Coupon\HotDeals\GetCouponQueue as GetHotDealsCouponQueue;
use Orbit\Notifications\Coupon\CouponNotAvailableNotification;
use Orbit\Notifications\Coupon\Sepulsa\VoucherNotAvailableNotification;
use Orbit\Notifications\Coupon\HotDeals\CouponNotAvailableNotification as HotDealsCouponNotAvailableNotification;

/**
 * Listen on:    `orbit.payment.postupdatepayment.after.commit`
 *
 * @author Budi <budi@dominopos.com>
 *
 * @param PaymentTransaction $payment - Instance of PaymentTransaction model
 */
Event::listen('orbit.payment.postupdatepayment.after.commit', function(PaymentTransaction $payment, $mall = null)
{
    // Clean up payment if expired, failed, or denied.
    if ($payment->expired() || $payment->failed() || $payment->denied()) {
        Log::info('PaidCoupon: PaymentID: ' . $payment->payment_transaction_id . ' failed/expired/denied.');

        DB::connection()->beginTransaction();

        $payment->cleanUp();

        DB::connection()->commit();
    }
    else if ($payment->status === PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED) {
        // This might be occurred because there are 2 transactions with same coupon and user.
        // Only the first transaction which pending/paid should get the coupon.
        Log::info("PaidCoupon: Payment {$payment->payment_transaction_id} success but can not issue coupon...");

        $failureMessage = "Transaction with the same user and coupon is still in progress.";
        $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);

        // Notify Admin that the voucher is failed and customer's money should be refunded.
        foreach($adminEmails as $email) {
            $devUser            = new User;
            $devUser->email     = $email;
            $devUser->notify(new CouponNotAvailableNotification($payment, $failureMessage));
        }

        // Notify customer that the coupon is not available and the money will be refunded.
        $paymentUser = new User;
        $paymentUser->email = $payment->user_email;
        if ($payment->forSepulsa()) {
            $paymentUser->notify(new VoucherNotAvailableNotification($payment));
        }
        else if ($payment->forHotDeals()) {
            $paymentUser->notify(new HotDealsCouponNotAvailableNotification($payment));
        }
    }
    else if ($payment->completed()) {
        Log::info('PaidCoupon: PaymentID: ' . $payment->payment_transaction_id . ' verified!');

        $queueData = ['paymentId' => $payment->payment_transaction_id, 'retries' => 0];
        if (! empty($mall)) {
            $queueData['mall_id'] = $mall->merchant_id;
        }

        // If we should delay the issuance...
        // TODO: maybe add new status to indicate that the coupon is in the process of issuing?
        if ($payment->forSepulsa() || $payment->paidWith(['bank_transfer', 'echannel'])) {
            $delay = Config::get('orbit.transaction.delay_before_issuing_coupon', 75);

            Log::info('PaidCoupon: Issuing coupon for PaymentID ' . $payment->payment_transaction_id . ' after ' . $delay . ' seconds...');

            // Determine which coupon we will issue...
            $queue = 'Orbit\\Queue\\Coupon\\HotDeals\\GetCouponQueue';
            if ($payment->forSepulsa()) {
                $queue = 'Orbit\\Queue\\Coupon\\Sepulsa\\GetCouponQueue';
            }

            Queue::connection('sync')
                ->later(
                    $delay, $queue,
                    $queueData
                );
        }
        else {
            // Otherwise, issue the coupon right away!
            Log::info('PaidCoupon: Issuing coupon directly for PaymentID ' . $payment->payment_transaction_id . '...');

            (new GetHotDealsCouponQueue())->fire(null, $queueData);
        }
    }
});
