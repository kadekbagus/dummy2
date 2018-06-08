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

// Notifications
// use Orbit\Notifications\Coupon\IssuedCouponNotification;
use Orbit\Notifications\Coupon\Sepulsa\ReceiptNotification as SepulsaReceiptNotification;
use Orbit\Notifications\Coupon\HotDeals\ReceiptNotification as HotDealsReceiptNotification;

/**
 * Listen on:    `orbit.payment.postupdatepayment.after.save`
 *
 * @author Budi <budi@dominopos.com>
 *
 * @param PaymentTransaction $payment - Instance of PaymentTransaction model
 */
Event::listen('orbit.payment.postupdatepayment.after.save', function($payment)
{
    // If payment completed...
    if ($payment->completed()) {

        // For sepulsa deals...
        if ($payment->forSepulsa()) {

            // If coupon issued, then do nothing.
            if (! empty($payment->issued_coupon)) {
                return;
            }

            // If not issued, then issue one.
            $voucherToken = $payment->coupon_sepulsa->token;

            // Take voucher
            $takenVouchers = TakeVoucher::create()->take($payment->payment_transaction_id, [['token' => $voucherToken]]);
            $takenVouchers = new TakeVoucherResponse($takenVouchers);

            if ($takenVouchers->isValid() && $takenVouchers->isSuccess()) {
                $takenVoucherData = $takenVouchers->getVoucherData();

                $issuedCoupon = new IssuedCoupon;

                $issuedCoupon->redeem_verification_code       = $takenVoucherData->id;
                $issuedCoupon->promotion_id       = $payment->object_id;
                $issuedCoupon->transaction_id     = $payment->payment_transaction_id;
                $issuedCoupon->user_id            = $payment->user_id;
                $issuedCoupon->user_email         = $payment->user_email;
                $issuedCoupon->issued_coupon_code = $takenVoucherData->code; // see todos
                $issuedCoupon->url                = $takenVoucherData->redeem_url; // see todos
                $issuedCoupon->issued_date        = $takenVoucherData->taken_date;
                $issuedCoupon->expired_date       = $takenVoucherData->expired_date;
                $issuedCoupon->issuer_user_id     = $payment->coupon->created_by;
                $issuedCoupon->status             = 'issued';
                $issuedCoupon->record_exists      = 'Y';

                $issuedCoupon->save();

                // Update payment transaction
                $payment->coupon_redemption_code = $takenVoucherData->code;
                // $payment->notes = ''; // clear the notes?
                $payment->save();
            }
            else {
                // Record failure...
                $paymentNotes = $payment->notes;
                $payment->notes = $paymentNotes . "--- " . $takenVouchers->getMessage() . "\n";

                // success_no_coupon means the payment was success but we can not get/take the coupon from Sepulsa API
                // either it is not available (all taken) or inactive.
                $payment->status = 'success_no_coupon';

                $payment->save();

                $errorMessage = sprintf('Request TakenVoucher to Sepulsa is failed. CouponID: %s --- Message: %s', $payment->object_id, $takenVouchers->getMessage());
                throw new Exception($errorMessage, 500);
            }
        }
        else if ($payment->forHotDeals()) {
            // For hot-deals, issuing a coupon is updating the status to 'issued' (after 'available')
            // (This means, the issued coupon record is already exists and is 'available')
            $issuedCoupon = IssuedCoupon::where('promotion_id', $payment->object_id)
                                            ->where('status', 'available')
                                            ->first();

            if (empty($issuedCoupon)) {
                throw new Exception('No issued coupons is available for purchase.', 500);
            }

            // Claim the coupon...
            $issuedCoupon->transaction_id     = $payment->payment_transaction_id;
            $issuedCoupon->user_id            = $payment->user_id;
            $issuedCoupon->user_email         = $payment->user_email;
            $issuedCoupon->issued_date        = Carbon::now('UTC');
            $issuedCoupon->status             = 'issued';
            $issuedCoupon->record_exists      = 'Y';

            $issuedCoupon->save();

            $payment->coupon_redemption_code = $issuedCoupon->issued_coupon_code;
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
Event::listen('orbit.payment.postupdatepayment.after.commit', function($payment)
{
    // If payment completed and coupon issued.
    if ($payment->completed()) {

        // Reload issued coupon relationship.
        $payment->load('issued_coupon');

        // Notify user for the IssuedCoupon detail...
        // $payment->user->notify(new IssuedCouponNotification($payment->issued_coupon, $payment));

        if ($payment->forSepulsa()) {
            $payment->user->notify(new SepulsaReceiptNotification($payment));
        }
        else if ($payment->forHotDeals()) {
            $payment->user->notify(new HotDealsReceiptNotification($payment));
        }
    }
});
