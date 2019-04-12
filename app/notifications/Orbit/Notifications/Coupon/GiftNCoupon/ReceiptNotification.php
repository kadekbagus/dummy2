<?php namespace Orbit\Notifications\Coupon\GiftNCoupon;

use Orbit\Notifications\Coupon\HotDeals\ReceiptNotification as BaseReceiptNotification;

use DB;
use Coupon;
use Config;
use Orbit\Helper\Util\CdnUrlGenerator;

/**
 * Receipt Notification for Customer after purchasing Gift N Coupon (Paid Coupon).
 *
 * @author Budi <budi@dominopos.com>
 */
class ReceiptNotification extends BaseReceiptNotification
{
    /**
     * Only send email at the moment.
     *
     * @return [type] [description]
     */
    protected function notificationMethods()
    {
        return ['email'];
    }

    /**
     * Get the email template for gift n coupon receipt.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.gift_n_coupon.receipt',
        ];
    }

    /**
     * Get email data for gift n coupon receipt.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'couponImage' => $this->getCouponImage(),
            'redeemUrls' => $this->getRedeemUrls(),
            'myPurchasesUrl' => $this->getMyPurchasesUrl(),
        ]);
    }

    /**
     * Get list of redeem urls depend on quantity being purchased.
     *
     * @return [type] [description]
     */
    private function getRedeemUrls()
    {
        return $this->payment->issued_coupons->lists('url');
    }

    /**
     * Get coupon image...Should be a query to table media.
     *
     * @return [type] [description]
     */
    private function getCouponImage()
    {
        // Default fallback image.
        $couponImage = 'https://www.gotomalls.com/images/campaign-default.png';

        $couponId = $this->payment->details->first()->object_id;
        $prefix = DB::getTablePrefix();
        $coupon = Coupon::select(DB::raw("{$prefix}promotions.promotion_id,
                                    {$prefix}promotions.promotion_name,
                                    {$prefix}campaign_account.mobile_default_language,
                                    CASE WHEN {$prefix}media.path is null THEN med.path ELSE {$prefix}media.path END as localPath,
                                    CASE WHEN {$prefix}media.cdn_url is null THEN med.cdn_url ELSE {$prefix}media.cdn_url END as cdnPath
                            "))
                            ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                            ->join('languages as default_languages', DB::raw('default_languages.name'), '=', 'campaign_account.mobile_default_language')
                            ->leftJoin('coupon_translations', function ($q) {
                                $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id');
                            })
                            ->leftJoin('coupon_translations as default_translation', function ($q) {
                                $q->on(DB::raw('default_translation.promotion_id'), '=', 'promotions.promotion_id')
                                    ->on(DB::raw('default_translation.merchant_language_id'), '=', DB::raw('default_languages.language_id'));
                            })
                        ->leftJoin(DB::raw("(SELECT m.path, m.cdn_url, ct.promotion_id
                                        FROM {$prefix}coupon_translations ct
                                        JOIN {$prefix}media m
                                            ON m.object_id = ct.coupon_translation_id
                                            AND m.media_name_long = 'coupon_translation_image_orig'
                                        GROUP BY ct.promotion_id) AS med"), DB::raw("med.promotion_id"), '=', 'promotions.promotion_id')
                        ->leftJoin('media', function ($q) {
                                $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                                $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                            })
                        ->where('promotions.promotion_id', '=', $couponId)
                        ->first();

        if ($coupon) {
            $attachmentPath = ! empty($coupon->localPath) ? $coupon->localPath : '';
            $cdnUrl = ! empty($coupon->cdnPath) ? $coupon->cdnPath : '';
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
            $couponImage = $imgUrl->getImageUrl($attachmentPath, $cdnUrl);
        }

        return $couponImage;
    }
}
