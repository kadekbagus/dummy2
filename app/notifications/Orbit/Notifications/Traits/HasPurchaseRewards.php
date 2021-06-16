<?php

namespace Orbit\Notifications\Traits;

use Carbon\Carbon;
use Config;
use Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use IssuedCoupon;
use PaymentTransaction;

/**
 * A trait that help getting purchase rewards.
 *
 * @author Budi <budi@dominopos.com>
 */
trait HasPurchaseRewards
{
    protected function getPurchaseRewards()
    {
        $rewards = [];

        // Re-query free coupons.
        $prefix = DB::getTablePrefix();
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        $couponImage = "CONCAT('{$urlPrefix}', {$prefix}media.path) as image_url";
        if ($usingCdn) {
            $couponImage = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT('{$urlPrefix}', {$prefix}media.path) ELSE {$prefix}media.cdn_url END as image_url";
        }

        $couponRewards = PaymentTransaction::onWriteConnection()
            ->whereHas('auto_issued_coupons', function($query) {
                $query->where('status', IssuedCoupon::STATUS_ISSUED)
                    ->where('transaction_id', $this->payment->payment_transaction_id);
            })
            ->select(
                'promotions.promotion_id as coupon_id',
                DB::raw('default_translation.promotion_name as name'),
                DB::raw($couponImage)
            )
            ->join('issued_coupons', 'payment_transactions.payment_transaction_id', '=', 'issued_coupons.transaction_id')
            ->join('promotions', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
            ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
            ->join('languages as default_languages', DB::raw('default_languages.name'), '=', 'campaign_account.mobile_default_language')
            ->leftJoin('coupon_translations as default_translation', function ($q) {
                $q->on(DB::raw('default_translation.promotion_id'), '=', 'promotions.promotion_id')
                ->on(DB::raw('default_translation.merchant_language_id'), '=', DB::raw('default_languages.language_id'));
            })
            ->leftJoin('media', function ($q) {
                $q->on('media.object_id', '=', DB::raw('default_translation.coupon_translation_id'));
                $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
            })
            ->where('payment_transaction_id', $this->payment->payment_transaction_id)
            ->where('promotions.status', 'active')
            ->where('promotions.end_date', '>=', Carbon::now('UTC')->format('Y-m-d H:i:s'))
            ->groupBy('promotions.promotion_id')
            ->get();

        foreach($couponRewards as $reward) {
            $rewards['coupon'][] = [
                'id' => $reward->coupon_id,
                'name' => $reward->name,
                'image_url' => $reward->image_url,
            ];
        }

        return $rewards;
    }
}
