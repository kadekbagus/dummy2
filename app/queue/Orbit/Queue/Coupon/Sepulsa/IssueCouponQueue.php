<?php namespace Orbit\Queue\Coupon\Sepulsa;

use DB;
use Log;
use Event;
use Queue;
use Config;
use Exception;
use Carbon\Carbon;
use Orbit\FakeJob;
use Orbit\Helper\Util\JobBurier;

use PaymentTransaction;

use Orbit\Helper\Sepulsa\API\TakeVoucher;
use Orbit\Helper\Sepulsa\API\Responses\TakeVoucherResponse;

/**
 * A job to issue Sepulsa Voucher after payment completed.
 *
 * @author Budi <budi@dominopos.com>
 */
class IssueCouponQueue
{
    /**
     * 
     * 
     * @param  [type] $job  [description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function fire($job, $data)
    {
        try {
            $paymentId = $data['paymentId'];

            $payment = PaymentTransaction::with(['coupon', 'coupon_sepulsa', 'issued_coupon', 'user'])
                                            ->where('payment_transaction_id', $paymentId)->first();
            $voucherToken = $payment->coupon_sepulsa->token;

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

        } catch (Exception $e) {
            DB::connection()->rollback();
            Log::info(sprintf('Request TakeVoucher retry exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
        }
    }
}
