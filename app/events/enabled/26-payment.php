<?php
/**
 * Event Listener related to Payment.
 *
 */

/**
 * Listen on:    `orbit.payment.postupdatepayment.after.commit`
 *
 * @author Budi <budi@dominopos.com>
 *
 * @param PaymentTransaction $payment - Instance of PaymentTransaction model
 */
Event::listen('orbit.payment.postupdatepayment.after.commit', function(PaymentTransaction $payment)
{
    if ($payment->expired() || $payment->failed() || $payment->denied()) {
        Log::info('PaidCoupon: PaymentID: ' . $payment->payment_transaction_id . ' failed/expired/denied.');

        DB::connection()->beginTransaction();

        // Clean up the payment...
        $payment->cleanUp();

        DB::connection()->commit();

        return;
    }

    if ($payment->completed()) {
        Log::info('PaidCoupon: PaymentID: ' . $payment->payment_transaction_id . ' verified! Issuing coupon in few seconds...');

        $delay = 15;

        $paymentInfo = json_decode(unserialize($payment->payment_midtrans_info));
        if (! empty($paymentInfo)) {
            if ($paymentInfo->payment_type === 'bank_transfer' || $paymentInfo->payment_type === 'echannel') {
                $delay = Config::get('orbit.transaction.delay_before_issuing_coupon', 60);
            }
        }

        // Push a job to Queue to get the Coupon.
        $queue = 'Orbit\\Queue\\Coupon\\HotDeals\\GetCouponQueue';
        if ($payment->forSepulsa()) {
            $queue = 'Orbit\\Queue\\Coupon\\Sepulsa\\GetCouponQueue';
        }

        Queue::later(
            $delay,
            $queue,
            ['paymentId' => $payment->payment_transaction_id, 'retries' => 0]
        );
    }
});
