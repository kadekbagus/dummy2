<?php namespace Orbit\Queue\PromoCode;

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
 * @author Budi <budi@dominopos.com>
 */
class CheckReservedPromoCode
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
            $issuedPromoCodes = isset($data['discount_codes']) ? $data['discount_codes'] : [];

            DB::connection()->beginTransaction();

            // TODO: what if the customer in the process of completing payment in snap window?
            // We still "remove" the issued coupons which will results to success payment but without coupon.
            if (! empty($issuedPromoCodes)) {
                $canceledPromoCodes = DiscountCode::onWriteConnection()->where(function($query) {
                                                    $query->whereHas('payment', function($payment) {
                                                        $payment->where('status', PaymentTransaction::STATUS_STARTING);
                                                    })
                                                    ->orWhere(function($query) {
                                                        $query->whereNull('payment_transaction_id')->orWhere('payment_transaction_id', '');
                                                    });
                                                })
                                                ->where('user_id', $userId)
                                                ->whereIn('discount_code_id', $issuedPromoCodes)
                                                ->get();

                if ($canceledPromoCodes->count() > 0) {
                    foreach($canceledPromoCodes as $canceledPromoCode) {
                        $canceledPromoCode->makeAvailable();
                    }

                    Log::info("Queue CheckReservedPromoCode Runnning: Promo Code canceled... status reverted to available.");
                }
                else {
                    Log::info("Queue CheckReservedPromoCode Runnning: Purchase was aborted/processed... Nothing to do.");
                }
            }

            DB::connection()->commit();

        } catch (Exception $e) {
            DB::connection()->rollback();
            Log::info(sprintf('Request check reserved coupon queue exception: %s:%s, %s', $e->getFile(), $e->getLine(), $e->getMessage()));
            Log::info("Request check reserved coupon queue exception data: " . serialize($data));
        }

        $job->delete();
    }
}
