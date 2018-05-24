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
 * Purpose:      Create issued coupon if needed
 *
 * @author Budi <budi@dominopos.com>
 *
 * @param PaymentMidtransUpdateAPIController $controller - The instance of the PaymentMidtransUpdateAPIController or its subclass
 * @param PaymentTransaction $payment - Instance of PaymentTransaction model
 *
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

            $invoice = null;

            // For sepulsa deals...
            if ($payment->forSepulsa()) {

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

                    // Update payment transaction
                    $payment->coupon_redemption_code = $takenVoucherData->code;
                    $payment->save();

                    // Set redeem url
                    $payment->redeem_url = $takenVoucherData->redeem_url;

                    // Create invoice for customer
                    $invoice = new SepulsaInvoiceNotification($payment);
                }
                else {
                    $errorMessage = sprintf('Taken Voucher request to Sepulsa failed. CouponID = %s. %s', $payment->object_id, $takenVouchers->getMessage());
                    throw new Exception($errorMessage, 500);
                }
            }
            else {
                // @todo other type of coupon, e.g. Hot Deals/Paid Coupon
                
                // Get coupon detail...
                
                // Prepare issuedCoupon data...
                
                // Create invoice for customer...
            }

            // Issue the coupon and notify user
            if (! empty($issuedCouponData)) {

                $issuedCoupon = new IssuedCoupon;
                foreach($issuedCouponData as $field => $value) {
                    $issuedCoupon->{$field} = $value;
                }
                $issuedCoupon->save();

                // Notify user...
                $payment->user->notify($invoice);
            }
        }

        // Coupon already issued...
    }

    // @todo add always-do tasks here...
});
