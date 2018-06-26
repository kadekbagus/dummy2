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
    if ($payment->expired() || $payment->failed() || $payment->pending()) {
        Log::info('PaidCoupon: Payment failed/expired/pending. Nothing to do.');
        return;
    }

    if ($payment->completed()) {
        Log::info('PaidCoupon: Payment verified! Issuing coupon...');

        // Push a job to Queue to get the Coupon.
        $queue = 'Orbit\\Queue\\Coupon\\HotDeals\\GetCouponQueue';
        $delay = 1;
        if ($payment->forSepulsa()) {
            $queue = 'Orbit\\Queue\\Coupon\\Sepulsa\\GetCouponQueue';
            $delay = 3;
        }

        Queue::later(
            $delay, 
            $queue,
            ['paymentId' => $payment->payment_transaction_id, 'retries' => 0],
            Config::get('queue.coupon', 'coupon')
        );
    }
});
