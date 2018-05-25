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
use Orbit\Notifications\Sepulsa\InvoiceNotification as SepulsaInvoiceNotification;

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

        // If no IssuedCoupon found for given User and Coupon, then do Taken Voucher request.
        if (empty($payment->issued_coupon)) {

            // For sepulsa deals...
            if ($payment->forSepulsa()) {

                $voucherToken = $payment->coupon_sepulsa->token;

                // Take voucher
                $takenVouchers = TakeVoucher::create()->take($payment->payment_transaction_id, [['token' => $voucherToken]]);
                $takenVouchers = new TakeVoucherResponse($takenVouchers);

                if ($takenVouchers->isValid() && $takenVouchers->isSuccess()) {
                    $takenVoucherData = $takenVouchers->getVoucherData();

                    $issuedCoupon = new IssuedCoupon;

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
                    $payment->save();
                }
                else {
                    $errorMessage = sprintf('Taken Voucher request to Sepulsa failed. CouponID = %s. %s', $payment->object_id, $takenVouchers->getMessage());
                    throw new Exception($errorMessage, 500);
                }
            }
            else {
                // @todo other type of coupon, e.g. Hot Deals/Paid Coupon
                
                // Call controller/handler we already have
            }
        }

        // Coupon already issued...
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
        // $payment->load('issued_coupon');

        // Create and send invoice to customer
        if ($payment->forSepulsa()) {
            $payment->user->notify(new SepulsaInvoiceNotification($payment));
        }
    }
});
