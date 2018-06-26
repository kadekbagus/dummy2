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

use PaymentTransaction;
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

            DB::connection()->beginTransaction();

            // Check reserved coupon status per user
            $userIssuedCoupon = IssuedCoupon::where('user_id', $userId)
                                            ->where('transaction_id', NULL)
                                            ->where('promotion_id', $couponId)
                                            ->where('status', IssuedCoupon::STATUS_ISSUED)
                                            ->delete();

            if ($userIssuedCoupon) {
                // If coupon didn't pay, change the issued coupon status and return the available coupon (+1)
                $availableCoupon = Coupon::where('promotion_id', $couponId)
                                    ->first();

                // Update available coupon +1
                $availableCoupon->available = $availableCoupon->available + 1;
                $availableCoupon->setUpdatedAt($availableCoupon->freshTimestamp());
                $availableCoupon->save();


                // Re sync the coupon data to make sure deleted when coupon sold out
                if ($availableCoupon->available > 0) {
                    // Re sync the coupon data
                    Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
                        'coupon_id' => $couponId
                    ]);
                } elseif ($availableCoupon->available == 0) {
                    // Delete the coupon and also suggestion
                    Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponDeleteQueue', [
                        'coupon_id' => $couponId
                    ]);

                    Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponSuggestionDeleteQueue', [
                        'coupon_id' => $couponId
                    ]);
                }

                Log::info(' Deleted unpay coupon with id = ' . $couponId . ', user id = ' . $userId . ' at ' . date('Y-m-d H:i:s'));

            }

            DB::connection()->commit();

            $job->delete();

            // Bury the job for later inspection
            // JobBurier::create($job, function($theJob) {
            //     // The queue driver does not support bury.
            //     $theJob->delete();
            // })->bury();

        } catch (Exception $e) {
            DB::connection()->rollback();
            Log::info(sprintf('Request check reserved coupon exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
        }
    }
}
