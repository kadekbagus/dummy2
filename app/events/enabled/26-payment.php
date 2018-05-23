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
use Orbit\Helper\Sepulsa\API\Responses\TakeVoucherResponse;

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

        // Check if this coupon is issued...
        $issuedCoupon = IssuedCoupon::where('user_id', $payment->user_id)
                                    ->where('user_email', $payment->user_email)
                                    ->where('promotion_id', $payment->object_id)
                                    ->first();

        // If no IssuedCoupon found for given User and Coupon, then do Taken Voucher request.
        if (empty($issuedCoupon)) {

            $issuedCouponData = [];

            // For sepulsa deals...
            if ($payment->coupon->promotion_type === 'sepulsa') {

                if (! empty($payment->coupon_sepulsa)) {

                    $voucherToken = $payment->coupon_sepulsa->token;

                    // Take voucher
                    $takenVouchers = TakeVoucher::create()->take($payment->payment_transaction_id, [['token' => $voucherToken]]);
                    $takenVouchers = new TakeVoucherResponse($takenVouchers);

                    if ($takenVouchers->isValid() && $takenVouchers->isSuccess()) {
                        $takenVoucherData = $takenVouchers->getVoucherData();

                        $issuedCouponData['promotion_id']       = $payment->object_id;
                        $issuedCouponData['transaction_id']     = $payment->payment_transaction_id;
                        $issuedCouponData['user_id']            = $payment->user_id;
                        $issuedCouponData['user_email']         = $payment->user_email;
                        $issuedCouponData['issued_coupon_code'] = $takenVoucherData->code; // see todos
                        $issuedCouponData['url']                = $takenVoucherData->redeem_url; // see todos
                        $issuedCouponData['issued_date']        = $takenVoucherData->taken_date;
                        $issuedCouponData['expired_date']       = $takenVoucherData->expired_date;
                        $issuedCouponData['issuer_user_id']     = $payment->coupon->created_by;
                        $issuedCouponData['status']             = 'issued';
                        $issuedCouponData['record_exists']      = 'Y';

                        // if (! empty($takenVoucherData->redeem_url)) {
                            // Send email with redeem_url for offline redeem...
                            // See todos
                        // }
                    }
                    else {
                        $errorMessage = sprintf('Taken Voucher request to Sepulsa failed. CouponID = %s. %s', $payment->object_id, $takenVouchers->getMessage());
                        throw new Exception($errorMessage, 500);
                    }
                }
                // Coupon Sepulsa not found.
            }
            else {
                // For other deals (e.g Hot Deals)
            }

            if (! empty($issuedCouponData)) {

                $issuedCoupon = new IssuedCoupon;
                foreach($issuedCouponData as $field => $value) {
                    $issuedCoupon->{$field} = $value;
                }
                $issuedCoupon->save();

                $payment->coupon_redemption_code = $issuedCoupon->issued_coupon_code;
                $payment->save();
            }
        }

        // Coupon already issued...
    }

    // If not complete..
});
