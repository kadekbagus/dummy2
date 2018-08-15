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

/**
 * Queue to check reserved coupon and returning the available coupon value if user cancel . . .
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */
class CheckReservedCoupon
{
    /**
     * It is basically re-fire the paymentupdate events after some delay...
     * No need to do any magic here.
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
            $cancelReservedCoupon = false;

            DB::connection()->beginTransaction();

            // Check detail coupon
            $coupon = Coupon::where('promotion_id', $couponId)->first();

            if (! empty($coupon)) {
                $cancelReservedCoupons = IssuedCoupon::where('user_id', $userId)
                                                ->where('transaction_id', NULL)
                                                ->where('promotion_id', $couponId)
                                                ->where('status', IssuedCoupon::STATUS_RESERVED)
                                                ->get();

                if (! empty($cancelReservedCoupons)) {

                    $isCancelReservedCoupon = FALSE;

                    foreach($cancelReservedCoupons as $cancelReservedCoupon) {
                        // Action based on promotion_type
                        if ($coupon->promotion_type === Coupon::TYPE_SEPULSA) {
                            $cancelReservedCoupon->delete(TRUE);

                            $isCancelReservedCoupon = TRUE;
                        } elseif ($coupon->promotion_type === Coupon::TYPE_HOT_DEALS) {
                            $cancelReservedCoupon->user_id     = NULL;
                            $cancelReservedCoupon->user_email  = NULL;
                            $cancelReservedCoupon->issued_date = NULL;
                            $cancelReservedCoupon->status      = IssuedCoupon::STATUS_AVAILABLE;
                            $cancelReservedCoupon->save();

                            $isCancelReservedCoupon = TRUE;
                        }
                    }

                    // Update available coupon and es data
                    if ($isCancelReservedCoupon) {

                        Log::info('Queue CheckReservedCoupon Runnning : Coupon unpay canceled, coupon_id = ' . $couponId . ', user id = ' . $userId . ' at ' . date('Y-m-d H:i:s'));

                    } else {

                        Log::info('Queue CheckReservedCoupon Runnning : No coupon canceled, coupon_id = ' . $couponId . ', user id = ' . $userId . ' at ' . date('Y-m-d H:i:s'));

                    }
                } else {
                    Log::info('Queue CheckReservedCoupon Runnning : No coupon canceled, coupon_id = ' . $couponId . ', user id = ' . $userId . ' at ' . date('Y-m-d H:i:s'));
                }

            }

            DB::connection()->commit();

        } catch (Exception $e) {
            DB::connection()->rollback();
            Log::info(sprintf('Request check reserved coupon queue exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
        }

        $job->delete();
    }
}
