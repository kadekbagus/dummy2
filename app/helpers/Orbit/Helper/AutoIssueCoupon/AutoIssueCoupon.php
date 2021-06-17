<?php

namespace Orbit\Helper\AutoIssueCoupon;

use Coupon;
use Exception;
use IssuedCoupon;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Helper which handle free/auto-issued coupon if given transaction meet
 * certain criteria.
 *
 * @author Budi <budi@gotomalls.com>
 */
class AutoIssueCoupon
{
    /**
     * Auto issue a free coupon if given product-type/payment meet the criteria.
     *
     * @param PaymentTransaction $payment trx instance
     * @param string $productType the product type (pulsa, pln, game_voucher)
     * @return void
     */
    public static function issue($payment, $productType = null)
    {
        // Resolve product type from payment if product type empty/not supplied
        // in the arg.
        $productType = ! empty($productType)
            ? $productType : self::getProductType($payment);

        // Convert to pln? because on digital product table we use 'electricity'
        // as product_type.
        $productType = $productType === 'electricity' ? 'pln' : $productType;

        // Make sure to process supported product types.
        if (! in_array($productType, ['pulsa', 'pln', 'game_voucher'])) {
            throw new Exception("Invalid productType: {$productType} !");
        }

        DB::transaction(function() use ($productType, $payment) {
            $coupons = Coupon::with(['issued_coupon' => function($query) {
                    $query->where('status', IssuedCoupon::STATUS_AVAILABLE);
                }])
                ->whereHas('issued_coupon', function($query) {
                    $query->where('status', IssuedCoupon::STATUS_AVAILABLE);
                })
                ->where("auto_issued_on_{$productType}", 1)
                ->where(function($query) use ($productType, $payment) {
                    // might use case when to cast null min_purchase to 0 ?
                    $query->whereNull("min_purchase_{$productType}")
                        ->orWhere("min_purchase_{$productType}", "<=", $payment->amount);
                })
                ->where('status', 'active')
                ->where('end_date', '>=', Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s'))
                ->orderBy("min_purchase_{$productType}", 'desc')
                ->get();

            foreach($coupons as $coupon) {
                if (self::eligible($payment, $coupon)) {
                    $coupon->issued_coupon->transaction_id = $payment->payment_transaction_id;
                    $coupon->issued_coupon->is_auto_issued = 1;
                    $coupon->issued_coupon->status = IssuedCoupon::STATUS_ISSUED;
                    $coupon->issued_coupon->user_id = $payment->user_id;
                    $coupon->issued_coupon->user_email = $payment->user_email;
                    $coupon->issued_coupon->save();

                    (new AutoIssueCouponActivity($payment, $coupon))->record();

                    Log::info("AutoIssueCoupon: coupon {$coupon->promotion_id} issued for trx {$payment->payment_transaction_id}");
                }
            }
        });
    }

    /**
     * Determine if given user-product type-amount eligible to get free coupons.
     * Users can get multiple coupons from single purchase,
     * but only get 1 same coupon at the same time.
     *
     * @param PaymentTransaction $payment
     * @param Coupon $coupon
     * @return bool
     */
    public static function eligible($payment, $coupon)
    {
        // Check for unique coupon
        $hasUniqueCoupon = IssuedCoupon::join('promotions',
                'issued_coupons.promotion_id', '=', 'promotions.promotion_id'
            )
            ->where('user_id', $payment->user_id)
            ->where('issued_coupons.promotion_id', $coupon->promotion_id)
            ->where('promotions.is_unique_redeem', 'Y')
            ->first();

        if ($hasUniqueCoupon) {
            Log::info("AutoIssueCoupon: no coupon {$coupon->promotion_id}, it is unique and user already got one.");
            return false;
        }

        // Check if user already has the same coupon.
        $hasCoupon = IssuedCoupon::where('user_id', $payment->user_id)
            ->where(function($query) {
                $query->whereIn('issued_coupons.status', [
                    IssuedCoupon::STATUS_ISSUED,
                    IssuedCoupon::STATUS_RESERVED
                ]);
            })
            ->where('promotion_id', $coupon->promotion_id)
            ->first();

        if ($hasCoupon) {
            Log::info("AutoIssuedCoupon: no coupon {$coupon->promotion_id}, user already got the coupon.");
            return false;
        }

        // Skip issued/redeemed check if both are unlimited.
        if ($coupon->maximum_redeem === 0 && $coupon->maximum_issued_coupon === 0) {
            return true;
        }

        // Check if reached max issued/redeemed count.
        $prefix = DB::getTablePrefix();
        $usedCount = Coupon::select(DB::raw("
                (select count({$prefix}issued_coupons.issued_coupon_id)
                    from {$prefix}issued_coupons
                    where {$prefix}issued_coupons.status in ('issued', 'reserved')
                        and promotion_id = '{$coupon->promotion_id}'
                ) as issued,
                (select count({$prefix}issued_coupons.issued_coupon_id)
                    from {$prefix}issued_coupons
                    where {$prefix}issued_coupons.status = 'redeemed'
                        and promotion_id = '{$coupon->promotion_id}'
                ) as redeemed
            "))
            ->where('promotion_id', $coupon->promotion_id)
            ->first();

        if ($coupon->maximum_issued_coupon > 0
            && $usedCount->issued >= $coupon->maximum_issued_coupon
        ) {
           Log::info("AutoIssueCoupon: no coupon {$coupon->promotion_id}, maximum issued reached.");
           return false;
        }

        if ($coupon->maximum_redeem > 0
            && $usedCount->redeemed >= $coupon->maximum_redeem
        ) {
            Log::info("AutoIssueCoupon: no coupon {$coupon->promotion_id}, maximum redeemed reached.");
            return false;
        }

        return true;
    }

    /**
     * Get product type of the given purchase.
     *
     * @return string $productType the product type of current purchase.
     */
    private static function getProductType($payment)
    {
        if (! isset($payment->details)) {
            $payment->load(['details.digital_product', 'details.pulsa']);
        }

        $productType = null;
        foreach($payment->details as $detail) {
            if (! empty($detail->pulsa)) {
                $productType = 'pulsa';
                break;
            }

            if (! empty($detail->digital_product)) {
                $productType = $detail->digital_product->product_type;
                break;
            }
        }

        return $productType;
    }
}
