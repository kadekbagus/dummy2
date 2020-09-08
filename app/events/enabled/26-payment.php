<?php

use Orbit\Notifications\Coupon\CouponNotAvailableNotification;
use Orbit\Notifications\Coupon\Sepulsa\CouponNotAvailableNotification as SepulsaCouponNotAvailableNotification;
use Orbit\Notifications\Coupon\HotDeals\CouponNotAvailableNotification as HotDealsCouponNotAvailableNotification;
use Orbit\Notifications\Payment\CanceledPaymentNotification;

/**
 * Listen on:    `orbit.payment.postupdatepayment.after.commit`
 *
 * @author Budi <budi@dominopos.com>
 *
 * @param PaymentTransaction $payment - Instance of PaymentTransaction model
 */
Event::listen('orbit.payment.postupdatepayment.after.commit', function(PaymentTransaction $payment, $mall = null, $paymentInfo = [])
{
    $paymentId = $payment->payment_transaction_id;
    $utm_source = (isset($payment->utm_source)) ? $payment->utm_source : '';
    $utm_medium = (isset($payment->utm_medium)) ? $payment->utm_medium : '';
    $utm_term = (isset($payment->utm_term)) ? $payment->utm_term : '';
    $utm_content = (isset($payment->utm_content)) ? $payment->utm_content : '';
    $utm_campaign = (isset($payment->utm_campaign)) ? $payment->utm_campaign : '';

    $utmUrl = '?utm_source='.$utm_source.'&utm_medium='.$utm_medium.'&utm_term='.$utm_term.'&utm_content='.$utm_content.'&utm_campaign='.$utm_campaign;

    $queue = '';

    // Clean up payment if expired, failed, denied, or canceled.
    if ($payment->expired() || $payment->failed() || $payment->denied() || $payment->canceled()) {
        Log::info("PaidCoupon: PaymentID: {$paymentId} is {$payment->status}.");

        DB::connection()->beginTransaction();

        if (! $payment->forPulsa() && ! $payment->forDigitalProduct()) {
            $payment->cleanUp();
        }

        $payment->resetDiscount();

        DB::connection()->commit();
    }
    else if ($payment->status === PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED
        || $payment->status === PaymentTransaction::STATUS_SUCCESS_NO_PULSA_FAILED
        || $payment->status === PaymentTransaction::STATUS_SUCCESS_NO_PRODUCT_FAILED) {
        // This might be occurred because there are 2 transactions with same coupon and user.
        // Only the first transaction which pending/paid should get the coupon.
        Log::info("PaidCoupon: Payment {$paymentId} success but can not issue coupon...");

        $failureMessage = "No issued_coupon is linked to the payment {$paymentId}.";
        $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);

        // Notify Admin that the voucher is failed and customer's money should be refunded.
        foreach($adminEmails as $email) {
            $adminUser            = new User;
            $adminUser->email     = $email;
            $adminUser->notify(new CouponNotAvailableNotification($payment, $failureMessage));
        }

        // Notify customer that the coupon is not available and the money will be refunded.
        $paymentUser = new User;
        $paymentUser->email = $payment->user_email;
        if ($payment->forSepulsa()) {
            $paymentUser->notify(new SepulsaCouponNotAvailableNotification($payment));
        }
        else if ($payment->forHotDeals() || $payment->forGiftNCoupon()) {
            $paymentUser->notify(new HotDealsCouponNotAvailableNotification($payment));
        }
    }
    else if ($payment->completed()) {
        Log::info("PaidCoupon: PaymentID: {$paymentId} verified!");

        $queueData = [
            'paymentId' => $payment->payment_transaction_id,
            'retries' => 0,
            'current_url' => $utmUrl,
            'payment_info' => $paymentInfo,
        ];

        if (! empty($mall)) {
            $queueData['mall_id'] = $mall->merchant_id;
        }

        // If we should delay the issuance...
        // TODO: maybe add new status to indicate that the coupon is in the process of issuing?
        if ($payment->forPulsa()) {
            Log::info("Pulsa: Will try to purchase pulsa to MCash in a few seconds for {$paymentId} ...");

            // @notes shouldn't we use specific queue/tube for this?
            Queue::push(
                'Orbit\\Queue\\Pulsa\\GetPulsaQueue',
                $queueData //,
                // 'gtm_pulsa'
            );
        }
        else if ($payment->forDigitalProduct()) {
            Log::info("DigitalProduct: Will try to purchase digital product in a few seconds for {$paymentId} ...");

            $queue = 'Orbit\\Queue\\DigitalProduct\\GetDigitalProductQueue';

            if ($payment->forUPoint('dtu')) {
                $queue = 'Orbit\\Queue\\DigitalProduct\\GetUPointDTUProductQueue';
            }
            else if ($payment->forUPoint('voucher')) {
                $queue = 'Orbit\\Queue\\DigitalProduct\\GetUPointVoucherProductQueue';
            }

            Queue::connection('sync')->push($queue, $queueData);
        }
        else if ($payment->forSepulsa() || $payment->paidWith(['bank_transfer', 'echannel', 'gopay'])) {
            $delay = Config::get('orbit.transaction.delay_before_issuing_coupon', 75);

            Log::info("PaidCoupon: Issuing coupon for PaymentID {$paymentId} after {$delay} seconds...");

            // Determine which coupon we will issue...
            $queue = 'Orbit\\Queue\\Coupon\\HotDeals\\GetCouponQueue';
            if ($payment->forGiftNCoupon()) {
                $queue = 'Orbit\\Queue\\Coupon\\GiftNCoupon\\GetCouponQueue';
            }
            else if ($payment->forSepulsa()) {
                $queue = 'Orbit\\Queue\\Coupon\\Sepulsa\\GetCouponQueue';
            }

            Queue::connection('sync')->later(
                $delay, $queue,
                $queueData
            );
        }
        else {
            // Otherwise, issue the coupon right away!
            Log::info("PaidCoupon: Issuing coupon directly for PaymentID {$paymentId} ...");

            $queue = 'Orbit\\Queue\\Coupon\\HotDeals\\GetCouponQueue';
            if ($payment->forGiftNCoupon()) {
                $queue = 'Orbit\\Queue\\Coupon\\GiftNCoupon\\GetCouponQueue';
            }

            Queue::connection('sync')->push(
                $queue,
                $queueData
            );
        }
    }
});

/**
 * Listen on:    `orbit.payment.postupdatepayment.after.commit`
 *
 * @author Budi <budi@dominopos.com>
 *
 * @param PaymentTransaction $payment - Instance of PaymentTransaction model
 */
Event::listen('orbit.payment-stripe.postupdatepayment.after.commit', function(PaymentTransaction $payment, $mall = null)
{
    $paymentId = $payment->payment_transaction_id;

    // Clean up payment if expired, failed, denied, or canceled.
    if ($payment->expired() || $payment->failed() || $payment->denied() || $payment->canceled()) {
        Log::info("PaidCoupon: PaymentID: {$paymentId} is {$payment->status}.");

        DB::connection()->beginTransaction();

        $payment->cleanUp();

        $payment->resetDiscount();

        DB::connection()->commit();
    }
    else if ($payment->status === PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED) {
        // This might be occurred because there are 2 transactions with same coupon and user.
        // Only the first transaction which pending/paid should get the coupon.
        Log::info("PaidCoupon: Payment {$paymentId} success but can not issue coupon...");

        $failureMessage = "No issued_coupon is linked to the payment {$paymentId}.";
        $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);

        // Notify Admin that the voucher is failed and customer's money should be refunded.
        foreach($adminEmails as $email) {
            $adminUser            = new User;
            $adminUser->email     = $email;
            $adminUser->notify(new CouponNotAvailableNotification($payment, $failureMessage));
        }

        // Notify customer that the coupon is not available and the money will be refunded.
        $paymentUser = new User;
        $paymentUser->email = $payment->user_email;
        if ($payment->forSepulsa()) {
            $paymentUser->notify(new SepulsaCouponNotAvailableNotification($payment));
        }
        else if ($payment->forHotDeals()) {
            $paymentUser->notify(new HotDealsCouponNotAvailableNotification($payment));
        }
    }
    else if ($payment->completed()) {
        Log::info("PaidCoupon: PaymentID: {$paymentId} verified!");

        $queueData = ['paymentId' => $payment->payment_transaction_id, 'retries' => 0, 'current_url' => $utmUrl];
        if (! empty($mall)) {
            $queueData['mall_id'] = $mall->merchant_id;
        }

        // If we should delay the issuance...
        // TODO: maybe add new status to indicate that the coupon is in the process of issuing?
        if ($payment->forSepulsa()) {
            $delay = Config::get('orbit.transaction.delay_before_issuing_coupon', 75);

            Log::info("PaidCoupon: Issuing coupon for PaymentID {$paymentId} after {$delay} seconds...");

            $queue = 'Orbit\\Queue\\Coupon\\Sepulsa\\GetCouponQueue';
            Queue::connection('sync')->later(
                $delay, $queue,
                $queueData
            );
        }
        else {
            // Otherwise, issue the coupon right away!
            Log::info("PaidCoupon: Issuing coupon directly for PaymentID {$paymentId} ...");

            Queue::connection('sync')->push(
                'Orbit\\Queue\\Coupon\\HotDeals\\GetCouponQueue',
                $queueData
            );
        }
    }
});
