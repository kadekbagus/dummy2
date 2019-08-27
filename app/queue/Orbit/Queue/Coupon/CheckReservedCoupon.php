<?php namespace Orbit\Queue\Coupon;

use DB;
use Log;
use Event;
use Queue;
use Config;
use Exception;
use Carbon\Carbon;
use Orbit\FakeJob;
use Orbit\Helper\Util\JobBurier;
use IssuedCoupon;
use Coupon;
use PaymentTransaction;
use DiscountCode;

/**
 * Queue to check reserved coupon and returning the available coupon value if user cancel . . .
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */
class CheckReservedCoupon
{
    /**
     * Queue handler method.
     *
     * @param  [type] $job  [description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function fire($job, $data)
    {
        try {
            $userId = $data['user_id'];
            $couponId = $data['coupon_id'];
            $issuedCoupons = isset($data['issued_coupons']) ? $data['issued_coupons'] : [];
            $cancelReservedCoupon = false;

            DB::connection()->beginTransaction();

            // Check detail coupon
            $coupon = Coupon::findOrFail($couponId);

            // TODO: what if the customer in the process of completing payment in snap window?
            // We still "remove" the issued coupons which will results to success payment but without coupon.
            if (! empty($issuedCoupons)) {
                $canceledCoupons = IssuedCoupon::where(function($query) {
                                                    $query->whereHas('payment', function($payment) {
                                                        $payment->where('status', PaymentTransaction::STATUS_STARTING);
                                                    })
                                                    ->orWhere(function($query) {
                                                        $query->whereNull('transaction_id')->orWhere('transaction_id', '');
                                                    });
                                                })
                                                ->where('user_id', $userId)
                                                ->whereIn('issued_coupon_id', $issuedCoupons)
                                                ->get();

                if ($canceledCoupons->count() > 0) {
                    $transactionId = null;
                    foreach($canceledCoupons as $canceledCoupon) {
                        $transactionId = $canceledCoupon->transaction_id;
                        if ($coupon->promotion_type === Coupon::TYPE_SEPULSA) {
                            $canceledCoupon->delete(TRUE);
                        }
                        else {
                            $canceledCoupon->makeAvailable();
                        }
                    }

                    // Unreserve related promo codes (if there any)...
                    if (! empty($transactionId)) {
                        $reservedPromoCodes = DiscountCode::where('status', 'reserved')
                            ->where('payment_transaction_id', $transactionId)
                            ->where('user_id', $userId)
                            ->where('object_id', $couponId)
                            ->where('object_type', 'coupon')
                            ->get();

                        foreach($reservedPromoCodes as $reservedPromoCode) {
                            $reservedPromoCode->makeAvailable();
                        }
                    }

                    Log::info('Queue CheckReservedCoupon Runnning : Coupon unpay canceled, coupon_id = ' . $couponId . ', user id = ' . $userId . ' at ' . date('Y-m-d H:i:s'));
                }
                else {
                    Log::info('Queue CheckReservedCoupon Runnning : No coupon canceled, coupon_id = ' . $couponId . ', user id = ' . $userId . ' at ' . date('Y-m-d H:i:s'));
                }
            }

            // Update coupon availability
            // $coupon->updateAvailability();

            DB::connection()->commit();

        } catch (Exception $e) {
            DB::connection()->rollback();
            Log::info(sprintf('Request check reserved coupon queue exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
            Log::info("Request check reserved coupon queue exception data: " . serialize($data));
        }

        $job->delete();
    }
}
