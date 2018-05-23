<?php
/**
 * Event listener for Coupon related events.
 *
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;
use Carbon\Carbon as Carbon;
use Orbit\Helper\Util\CdnUrlGenerator;
use Orbit\Helper\OneSignal\OneSignal;

use Orbit\Helper\Sepulsa\API\TakeVoucher;
use Orbit\Helper\Sepulsa\API\VoucherOrder;
use Orbit\Helper\Sepulsa\API\Responses\VoucherOrderResponse;
use Orbit\Helper\Sepulsa\API\Responses\VoucherRedeemResponse;

/**
 * Listen on:    `orbit.payment.postupdatepayment.after.save`
 * Purpose:      Create issued coupon if needed
 *
 * @author Budi <budi@dominopos.com>
 *
 * @param PaymentMidtransUpdateAPIController $controller - The instance of the PaymentMidtransUpdateAPIController or its subclass
 * @param PaymentTransaction $payment - Instance of object Payment Transaction
 *
 * @todo make sure field 'issued_coupon_code' is for storing coupon code from Sepulsa
 * @todo make sure field 'url' is for storing redeem_url from Sepulsa
 * @todo Send email with redeem_url for offline redeem
 */
Event::listen('orbit.payment.postupdatepayment.after.save', function($payment)
{
    // If payment completed...
    if ($payment->completed()) {

        // For sepulsa deals...
        if ($payment->coupon->promotion_type === 'sepulsa') {

            if (! empty($payment->coupon_sepulsa)) {

                // Check if this coupon is issued...
                $issuedCoupon = IssuedCoupon::where('user_id', $payment->user_id)
                                            ->where('user_email', $payment->user_email)
                                            ->where('promotion_id', $payment->object_id)
                                            ->first();

                // If no IssuedCoupon found for given User and Coupon, then do Taken Voucher request.
                if (empty($issuedCoupon)) {

                    $voucherToken = $payment->coupon_sepulsa->token;

                    // Take voucher
                    $takenVouchers = TakeVoucher::create()->take($payment->payment_transaction_id, [['token' => $voucherToken]]);
                    $takenVouchers = new VoucherTakenResponse($takenVouchers);

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
                        $issuedCoupon->issuer_user_id     = $payment->coupon_sepulsa->coupon->created_by;
                        $issuedCoupon->status             = 'issued';
                        $issuedCoupon->record_exists      = 'Y';

                        $issuedCoupon->save();

                        // if (! empty($takenVoucherData->redeem_url)) {
                            // Send email with redeem_url for offline redeem...
                            // See todos
                        // }
                    }
                    else {
                        $errorMessage = sprintf('Taken Voucher request to Sepulsa failed. CouponID = %s. %s', $payment->object_id, $voucherOrder->getMessage());
                        throw new Exception($errorMessage, 500);
                    }
                }
                else {
                    // Coupon already issued...
                }
            }
            else {
                // Payment - Coupon Sepulsa relation not found.
                throw new Exception('Coupon not found.', 500);
            }
        }
        else {
            // For other deals (e.g Hot Deals)
            
        }
    }

    // If not complete..
});
