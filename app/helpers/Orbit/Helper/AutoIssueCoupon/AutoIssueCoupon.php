<?php

namespace Orbit\Helper\AutoIssueCoupon;

use Coupon;
use Exception;
use IssuedCoupon;
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

        // Limit the same user_email for getting auto-issued.
        if (! self::eligible($productType, $payment)) {
            return;
        }

        DB::transaction(function() use ($productType, $payment) {
            $coupon = Coupon::with(['issued_coupon' => function($query) {
                    $query->where('status', IssuedCoupon::STATUS_AVAILABLE);
                }])
                ->where('auto_issued_on_' . $productType, 1)
                ->where('min_purchase_' . $productType, '<=', $payment->amount)
                ->where('status', 'active')
                ->orderBy('min_purchase_' . $productType, 'desc')
                ->first();

            if (! empty($coupon) && ! empty($coupon->issued_coupon)) {
                $coupon->issued_coupon->transaction_id = $payment->payment_transaction_id;
                $coupon->issued_coupon->is_auto_issued = 1;
                $coupon->issued_coupon->status = IssuedCoupon::STATUS_ISSUED;
                $coupon->issued_coupon->user_id = $payment->user_id;
                $coupon->issued_coupon->user_email = $payment->user_email;
                $coupon->issued_coupon->save();

                (new AutoIssueCouponActivity($payment, $coupon))->record();
            }
        });
    }

    /**
     * Determine if given user-product type-amount eligible to get free coupons.
     * At the moment, limit the auto-issued coupon only once per purchase email.
     *
     * @param string $productType
     * @param PaymentTransaction $payment
     * @return bool
     */
    public static function eligible($productType, $payment)
    {
        // Check if there's coupon that match minimum purchase.
        $coupon = Coupon::whereHas('issued_coupon', function($query) {
                $query->where('status', IssuedCoupon::STATUS_AVAILABLE);
            })
            ->where('auto_issued_on_' . $productType, 1)
            ->where('min_purchase_' . $productType, '<=', $payment->amount)
            ->where('status', 'active')
            ->orderBy('min_purchase_' . $productType, 'desc')
            ->first();

        if (empty($coupon)) {
            Log::info("AutoIssuedCoupon: no coupons meet the criteria.");
            return false;
        }

        // Check if user already has the same coupon.
        $hasCoupon = IssuedCoupon::where('user_id', $payment->user_id)
            ->whereIn('issued_coupons.status', [
                IssuedCoupon::STATUS_ISSUED,
                IssuedCoupon::STATUS_RESERVED,
                IssuedCoupon::STATUS_REDEEMED
            ])
            ->where('promotion_id', $coupon->promotion_id)
            ->first();

        if ($hasCoupon) {
            Log::info("AutoIssuedCoupon: no coupons, user already got the coupon.");
            return false;
        }

        // Check if reached max issued/redeemed count.
        $prefix = DB::getTablePrefix();
        $status = [
            'issued' => implode("','", [
                IssuedCoupon::STATUS_ISSUED,
                IssuedCoupon::STATUS_RESERVED
            ]),
            'redeemed' => IssuedCoupon::STATUS_REDEEMED,
        ];
        $usedCount = Coupon::select(DB::raw("
                (select count({$prefix}issued_coupons.issued_coupon_id)
                    from {$prefix}issued_coupons
                    where {$prefix}issued_coupons.status in ('{$status['issued']}')
                        and promotion_id = '{$coupon->promotion_id}'
                ) as issued,
                (select count({$prefix}issued_coupons.issued_coupon_id)
                    from {$prefix}issued_coupons
                    where {$prefix}issued_coupons.status = '{$status['redeemed']}'
                        and promotion_id = '{$coupon->promotion_id}'
                ) as redeemed
            "))
            ->where('promotion_id', $coupon->promotion_id)
            ->first();

        if ($usedCount->issued >= $coupon->maximum_issued_coupon
            || $usedCount->redeemed >= $coupon->maximum_redeem
        ) {
            Log::info("AutoIssueCoupon: no free coupons, maximum issued/redeemed reached.");
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
