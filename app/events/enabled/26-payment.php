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

/**
 * Listen on:    `orbit.payment.postupdatepayment.after.save`
 *
 * @author Budi <budi@dominopos.com>
 *
 * @param PaymentTransaction $payment - Instance of PaymentTransaction model
 */
Event::listen('orbit.payment.postupdatepayment.after.save', function(PaymentTransaction $payment, $retries = 0, $sendNotification = false)
{
    $notificationDelay = 5;
    // TODO: Move to config?
    $adminEmails = [
        Config::get('orbit.contact_information.developer.email', 'developer@dominopos.com'),
    ];

    // If payment completed...
    if ($payment->completed()) {

        // If coupon issued, do nothing...
        if ($payment->couponIssued()) {
            return;
        }

        // For sepulsa deals, we need to claim the voucher with TakeVoucher API.
        if ($payment->forSepulsa()) {

            $voucherToken = $payment->coupon_sepulsa->token;
            $paymentId = $payment->payment_transaction_id;

            // Take the voucher from Sepulsa...
            $takenVouchers = TakeVoucher::create()->take($paymentId, [['token' => $voucherToken]]);
            $takenVouchers = new TakeVoucherResponse($takenVouchers);

            if ($takenVouchers->isValid() && $takenVouchers->isSuccess()) {
                $takenVoucherData = $takenVouchers->getVoucherData();

                $issuedCoupon = new IssuedCoupon;

                $issuedCoupon->redeem_verification_code       = $takenVoucherData->id;
                $issuedCoupon->promotion_id       = $payment->object_id;
                $issuedCoupon->transaction_id     = $paymentId;
                $issuedCoupon->user_id            = $payment->user_id;
                $issuedCoupon->user_email         = $payment->user_email;
                $issuedCoupon->issued_coupon_code = $takenVoucherData->code;
                $issuedCoupon->url                = $takenVoucherData->redeem_url;
                $issuedCoupon->issued_date        = $takenVoucherData->taken_date;
                $issuedCoupon->expired_date       = $takenVoucherData->expired_date;
                $issuedCoupon->issuer_user_id     = $payment->coupon->created_by;
                $issuedCoupon->status             = IssuedCoupon::STATUS_ISSUED;
                $issuedCoupon->record_exists      = 'Y';

                $issuedCoupon->save();

                // Update payment transaction data
                $payment->coupon_redemption_code = $takenVoucherData->code;
                // $payment->notes = ''; // clear the notes?
                $payment->save();

                // Update availability
                $payment->coupon->updateAvailability();
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

                    $errorMessage = sprintf('TakeVoucher Request: First try failed. Status: FAILED, CouponID: %s --- Message: %s', $payment->object_id, $takenVouchers->getMessage());
                    Log::info($errorMessage);
                }

                // Let's retry TakeVoucher request...
                if ($retries < Config::get('orbit.partners_api.sepulsa.take_voucher_max_retry', 3)) {
                    $delay = Config::get('orbit.partners_api.sepulsa.take_voucher_retry_timeout', 30);

                    Queue::later(
                        $delay,
                        'Orbit\\Queue\\Coupon\\Sepulsa\\RetryTakeVoucherQueue', 
                        compact('paymentId', 'voucherToken', 'retries')
                    );

                    $errorMessage = sprintf('TakeVoucher Request: Retrying in %s seconds... Status: FAILED, CouponID: %s --- Message: %s', $delay, $payment->object_id, $takenVouchers->getMessage());
                }
                else {
                    // Oh, no more retry, huh?
                    // Notify Admin that the voucher is failed and customer's money should be refunded.
                    foreach($adminEmails as $email) {
                        $devUser            = new User;
                        $devUser->email     = $email;
                        $devUser->notify(new TakeVoucherFailureNotification($payment, $takenVouchers, $retries), $notificationDelay);
                    }

                    // Notify customer that the coupon is not available and the money will be refunded.
                    $payment->user->notify(new SepulsaVoucherNotAvailableNotification($payment), $notificationDelay);

                    $errorMessage = sprintf('TakeVoucher Request: Maximum Retry reached... Status: FAILED, CouponID: %s --- Message: %s', $payment->object_id, $takenVouchers->getMessage());
                }

                Log::info($errorMessage);
            }
        }
        else if ($payment->forHotDeals()) {
            // For hot-deals, issuing a coupon means updating the status to 'issued'
            
            // First look for reserved coupon by this user...
            $issuedCoupon = IssuedCoupon::where('promotion_id', $payment->object_id)->where('status', IssuedCoupon::STATUS_RESERVED)
                                          ->where('user_id', $payment->user_id)->first();

            if (empty($issuedCoupon)) {
                $issuedCoupon = IssuedCoupon::where('promotion_id', $payment->object_id)->available()->first();
            }

            // If no more coupon available to be purchased, then notify customer.
            if (empty($issuedCoupon)) {
                $payment->notes = 'Can not issue coupon anymore. Limit reached.';
                $payment->status = PaymentTransaction::STATUS_SUCCESS_NO_COUPON;

                $payment->user->notify(new HotDealsCouponNotAvailableNotification($payment), $notificationDelay);
            }
            else {
                // Claim the coupon...
                $issuedCoupon->transaction_id     = $payment->payment_transaction_id;
                $issuedCoupon->user_id            = $payment->user_id;
                $issuedCoupon->user_email         = $payment->user_email;
                $issuedCoupon->issued_date        = Carbon::now('UTC');
                $issuedCoupon->status             = IssuedCoupon::STATUS_ISSUED;
                $issuedCoupon->record_exists      = 'Y';

                $issuedCoupon->save();

                $payment->coupon_redemption_code = $issuedCoupon->issued_coupon_code;

                // Update availability
                Log::info('Updating coupon availability..');
                $payment->coupon->updateAvailability();
            }

            $payment->save();
        }
    }

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
    // If payment completed and coupon issued.
    if ($payment->completed() && $payment->couponIssued()) {

        if ($payment->forSepulsa()) {
            // Only send receipt if payment success and the coupon issued.
            $payment->user->notify(new SepulsaReceiptNotification($payment));
        }
        else if ($payment->forHotDeals()) {
            $payment->user->notify(new HotDealsReceiptNotification($payment));
        }
    }
});
