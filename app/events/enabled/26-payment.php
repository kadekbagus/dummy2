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
    // $notificationDelay = 5;

    // // TODO: Move to config?
    // $adminEmails = [
    //     Config::get('orbit.contact_information.developer.email', 'developer@dominopos.com'),
    // ];

    // if ($payment->expired()) {
    //     Log::info('Payment expired. Nothing to do.');
    //     return;
    // }

    // If payment completed...
    // if ($payment->completed()) {

    //     // If coupon issued, do nothing...
    //     if ($payment->couponIssued()) {
    //         return;
    //     }

    //     // Notify admin and customer if the coupon not available.
    //     if ($payment->coupon->notAvailable()) {
    //         Log::info('Coupon not available. Will notify admin and customer.');
    //         $errorMessage = 'Coupon might be expired, inactive, or no more coupon available for purchase.';

    //         // Notify Admin...
    //         foreach($adminEmails as $email) {
    //             $admin          = new User;
    //             $admin->email   = $email;
    //             $admin->notify(new CouponNotAvailableNotification($payment, $errorMessage), $notificationDelay);
    //         }

    //         // Notify customer...
    //         $payment->user->notify(new CustomerCouponNotAvailableNotification($payment), $notificationDelay);

    //         $payment->notes = $errorMessage;
    //         $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON_FAILED;
    //         $payment->save();

    //         // TODO: This update should be removed in the future.
    //         $payment->coupon->updateAvailability();

    //         return;
    //     }

    //     // Payment success, but at this point we dont issue the coupon yet.
    //     // We can push a job to Queue to do that and let the request end.
    //     $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON;
    //     $payment->save();
    // }

    // @todo add always-do tasks here...
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
    if ($payment->expired() || $payment->failed()) {
        Log::info('PaidCoupon: Payment failed/expired. Nothing to do.');
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
            $delay, $queue,
            ['paymentId' => $payment->payment_transaction_id, 'retries' => 0],
            Config::get('queue.coupon', 'coupon')
        );
    }

    // // If payment completed and coupon issued.
    // if ($payment->completed() && $payment->couponIssued()) {

    //     if ($payment->forSepulsa()) {
    //         // Only send receipt if payment success and the coupon issued.
    //         $payment->user->notify(new SepulsaReceiptNotification($payment));
    //     }
    //     else if ($payment->forHotDeals()) {
    //         $payment->user->notify(new HotDealsReceiptNotification($payment));
    //     }
    // }
});
