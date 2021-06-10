<?php

namespace Orbit\Helper\AutoIssueCoupon;

use Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

/**
 * Has Rewards for payment transaction.
 */
trait HasRewards
{
    public function auto_issued_coupons()
    {
        return $this->hasMany('IssuedCoupon', 'transaction_id')
            ->where('is_auto_issued', 1);
    }

    /**
     * Get rewards information from current transaction.
     *
     * @return void
     */
    public function getRewards()
    {
        $rewards = [];

        if ($this->auto_issued_coupons->count() > 0) {

            $images = $this->getImages($this->auto_issued_coupons);

            foreach($this->auto_issued_coupons as $issuedCoupon) {
                $rewards[] = (object) [
                    'object_type' => 'coupon',
                    'object_id' => $issuedCoupon->promotion_id,
                    'object_name' => $issuedCoupon->coupon->promotion_name,
                    'image_url' => isset($images[$issuedCoupon->promotion_id])
                        ? $images[$issuedCoupon->promotion_id] : '',
                    'status' => $issuedCoupon->status,
                ];
            }

            unset($this->auto_issued_coupons);
        }

        // other type of rewards should go here...

        if (! empty($rewards)) {
            $this->rewards = $rewards;
        }
    }

    private function getImages($issuedCoupons)
    {
        $prefix = DB::getTablePrefix();
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        $couponImage = "CONCAT('{$urlPrefix}', {$prefix}media.path) as image_url";
        if ($usingCdn) {
            $couponImage = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT('{$urlPrefix}', {$prefix}media.path) ELSE {$prefix}media.cdn_url END as image_url";
        }

        $images = [];
        $couponIds = [];
        foreach($issuedCoupons as $issuedCoupon) {
            $couponIds[] = $issuedCoupon->promotion_id;
        }

        $mediaList = Coupon::select(
                'promotions.promotion_id',
                DB::raw($couponImage)
            )
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
            ->whereIn('promotions.promotion_id', $couponIds)
            ->get();

        foreach($mediaList as $media) {
            $images[$media->promotion_id] = $media->image_url;
        }

        return $images;
    }
}
