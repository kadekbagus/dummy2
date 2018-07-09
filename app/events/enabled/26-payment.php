<?php

use Orbit\Queue\Coupon\HotDeals\GetCouponQueue as GetHotDealsCouponQueue;

/**
 * Listen on:    `orbit.payment.postupdatepayment.after.commit`
 *
 * @author Budi <budi@dominopos.com>
 *
 * @param PaymentTransaction $payment - Instance of PaymentTransaction model
 */
Event::listen('orbit.payment.postupdatepayment.after.commit', function(PaymentTransaction $payment)
{
    // Clean up payment if expired, failed, or denied.
    if ($payment->expired() || $payment->failed() || $payment->denied()) {
        Log::info('PaidCoupon: PaymentID: ' . $payment->payment_transaction_id . ' failed/expired/denied.');

        DB::connection()->beginTransaction();

        $payment->cleanUp();

        DB::connection()->commit();
    }
    else if ($payment->completed()) {
        Log::info('PaidCoupon: PaymentID: ' . $payment->payment_transaction_id . ' verified!');

        // If coupon is hot deals and the payment is credit_card, then issue coupon ASAP 
        // eventho it will make the request take longer to respond...
        if ($payment->forHotDeals() && $payment->paidWith(['credit_card'])) {
            Log::info('PaidCoupon: Issuing coupon directly for PaymentID ' . $payment->payment_transaction_id . '...');
            
            (new GetHotDealsCouponQueue())->fire(null, [
                'paymentId' => $payment->payment_transaction_id
            ]);
        }
        else {
            $delay = Config::get('orbit.transaction.delay_before_issuing_coupon', 90);

            Log::info('PaidCoupon: Issuing coupon for PaymentID ' . $payment->payment_transaction_id . ' after ' . $delay . ' seconds...');

            // Determine which coupon we will issue...
            $queue = 'Orbit\\Queue\\Coupon\\HotDeals\\GetCouponQueue';
            if ($payment->forSepulsa()) {
                $queue = 'Orbit\\Queue\\Coupon\\Sepulsa\\GetCouponQueue';
            }

            Queue::later(
                $delay, $queue,
                ['paymentId' => $payment->payment_transaction_id, 'retries' => 0]
            );
        }
    }
});
