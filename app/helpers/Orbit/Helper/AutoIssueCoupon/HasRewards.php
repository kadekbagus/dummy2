<?php

namespace Orbit\Helper\AutoIssueCoupon;

use Carbon\Carbon;
use Coupon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Language;

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

            $translations = $this->getTranslations($this->auto_issued_coupons);

            // can just loop thru translations, but will be missing
            // issued coupon 'status'.
            foreach($this->auto_issued_coupons as $issuedCoupon) {
                if (! isset($translations[$issuedCoupon->promotion_id])) {
                    continue;
                }

                $translation = $translations[$issuedCoupon->promotion_id];

                $rewards[] = (object) [
                    'object_type' => 'coupon',
                    'object_id' => $issuedCoupon->promotion_id,
                    'object_name' => $translation['name'],
                    'image_url' => $translation['image'],
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

    private function getTranslations($issuedCoupons)
    {
        $prefix = DB::getTablePrefix();
        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        $couponImage = "CONCAT('{$urlPrefix}', {$prefix}media.path)";
        $couponDefaultImage = "CONCAT('{$urlPrefix}', default_media.path)";
        if ($usingCdn) {
            $couponImage = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT('{$urlPrefix}', {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
            $couponDefaultImage = "CASE WHEN (default_media.cdn_url is null or default_media.cdn_url = '') THEN CONCAT('{$urlPrefix}', default_media.path) ELSE default_media.cdn_url END";
        }

        $lang = Input::get('language', 'id');
        $validLanguage = Language::where('status', '=', 'active')
                            ->where('name', $lang)
                            ->first();

        $translations = [];
        $couponIds = [];
        foreach($issuedCoupons as $issuedCoupon) {
            $couponIds[] = $issuedCoupon->promotion_id;
        }

        $translationList = Coupon::select(
                'promotions.promotion_id',
                'promotions.end_date',
                'promotions.status',
                DB::raw("
                    case when {$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null
                        then default_translation.promotion_name
                        else {$prefix}coupon_translations.promotion_name
                    end as promotion_name,
                    case when {$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null
                        then {$couponDefaultImage}
                        else {$couponImage}
                    end as image_url
                ")
            )
            ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
            ->join('languages as default_languages', DB::raw('default_languages.name'), '=', 'campaign_account.mobile_default_language')
            ->leftJoin('coupon_translations', function ($q) use ($validLanguage) {
                $q->on('promotions.promotion_id', '=', 'coupon_translations.promotion_id');
                $q->on('coupon_translations.merchant_language_id', '=', DB::raw("'{$validLanguage->language_id}'"));
            })
            ->leftJoin('media', function ($q) {
                $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
            })
            ->leftJoin('coupon_translations as default_translation', function ($q) {
                $q->on('promotions.promotion_id', '=', DB::raw('default_translation.promotion_id'));
                $q->on(DB::raw('default_translation.merchant_language_id'), '=', DB::raw('default_languages.language_id'));
            })
            ->leftJoin('media as default_media', function ($q) {
                $q->on(DB::raw('default_translation.coupon_translation_id'), '=', DB::raw('default_media.object_id'));
                $q->on(DB::raw('default_media.media_name_long'), '=', DB::raw("'coupon_translation_image_orig'"));
            })
            ->where('promotions.status', 'active')
            ->where('end_date', '>=', Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s'))
            ->whereIn('promotions.promotion_id', $couponIds)
            ->get();

        foreach($translationList as $translation) {
            $translations[$translation->promotion_id] = [
                'name' => $translation->promotion_name,
                'image' => $translation->image_url,
            ];
        }

        return $translations;
    }
}
