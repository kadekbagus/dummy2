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
                $cancelReservedCoupon = IssuedCoupon::where('user_id', $userId)
                                                ->where('transaction_id', NULL)
                                                ->where('promotion_id', $couponId)
                                                ->where('status', IssuedCoupon::STATUS_ISSUED)
                                                ->first();

                if (! empty($cancelReservedCoupon)) {
                    // Action based on promotion_type
                    if ($coupon->promotion_type === 'sepulsa') {
                        $cancelReservedCoupon->delete();
                    } elseif ($coupon->promotion_type === 'hot_deals') {
                        $cancelReservedCoupon->user_id     = NULL;
                        $cancelReservedCoupon->user_email  = NULL;
                        $cancelReservedCoupon->issued_date = NULL;
                        $cancelReservedCoupon->status      = 'available';
                        $cancelReservedCoupon->save();
                    }

                    // Update available coupon and es data
                    if ($cancelReservedCoupon) {

                        // Update available coupon +1
                        $coupon->available = $coupon->available + 1;
                        $coupon->setUpdatedAt($coupon->freshTimestamp());
                        $coupon->save();


                        // Re sync the coupon data to make sure deleted when coupon sold out
                        if ($coupon->available > 0) {
                            // Re sync the coupon data
                            Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
                                'coupon_id' => $couponId
                            ]);
                        } elseif ($coupon->available == 0) {
                            // Delete the coupon and also suggestion
                            Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponDeleteQueue', [
                                'coupon_id' => $couponId
                            ]);

                            Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponSuggestionDeleteQueue', [
                                'coupon_id' => $couponId
                            ]);

                            // To Do : Delete all coupon cache
                            if (Config::get('orbit.cache.ng_redis_enabled', FALSE)) {
                                $redis = Cache::getRedis();
                                $keyName = array('coupon','home');
                                foreach ($keyName as $value) {
                                    $keys = $redis->keys("*$value*");
                                    if (! empty($keys)) {
                                        foreach ($keys as $key) {
                                            $redis->del($key);
                                        }
                                    }
                                }
                            }

                        }

                        Log::info('Queue CheckReservedCoupon Runnning :  Canceled unpay coupon with id = ' . $couponId . ', user id = ' . $userId . ' at ' . date('Y-m-d H:i:s'));

                    } else {

                        Log::info('Queue CheckReservedCoupon Runnning : There is no coupon issued canceled = ' . $couponId . ', user id = ' . $userId . ' at ' . date('Y-m-d H:i:s'));

                    }
                } else {
                    Log::info(' Queue CheckReservedCoupon Runnning : There is no coupon issued canceled = ' . $couponId . ', user id = ' . $userId . ' at ' . date('Y-m-d H:i:s'));
                }

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
            Log::info(sprintf('Request check reserved coupon queue exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
        }
    }
}
