<?php
/**
 * Event Listener related to Payment.
 *
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;
use Carbon\Carbon as Carbon;
use Orbit\Helper\Util\CdnUrlGenerator;
use Orbit\Helper\OneSignal\OneSignal;

use Orbit\Helper\Sepulsa\API\TakeVoucher;
use Orbit\Helper\Sepulsa\API\Responses\TakeVoucherResponse;

// User Notifications
use Orbit\Notifications\Coupon\Sepulsa\ReceiptNotification as SepulsaReceiptNotification;
use Orbit\Notifications\Coupon\Sepulsa\TakeVoucherFailureNotification;
use Orbit\Notifications\Coupon\Sepulsa\VoucherNotAvailableNotification as SepulsaVoucherNotAvailableNotification;

use Orbit\Notifications\Coupon\HotDeals\ReceiptNotification as HotDealsReceiptNotification;
use Orbit\Notifications\Coupon\HotDeals\CouponNotAvailableNotification as HotDealsCouponNotAvailableNotification;

use Orbit\Notifications\Coupon\CouponNotAvailableNotification;
use Orbit\Notifications\Coupon\CustomerCouponNotAvailableNotification;

/**
 * Listen on:    `orbit.payment.postupdatepayment.after.save`
 *
 * @author Budi <budi@dominopos.com>
 *
 * @param PaymentTransaction $payment - Instance of PaymentTransaction model
 */
Event::listen('orbit.payment.postupdatepayment.after.save', function(PaymentTransaction $payment, $retries = 0, $sendNotification = false)
{

});

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

        // Clean up the payment...
        $payment->cleanUp();

        return;
    }

    if ($payment->completed()) {
        Log::info('PaidCoupon: PaymentID: ' . $payment->payment_transaction_id . ' verified! Issuing coupon in few seconds...');

        $delay = 3;

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
