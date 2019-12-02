<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Notifications;

use Illuminate\Support\Facades\Config;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Notifications\CouponTransferNotification;
use Orbit\Helper\Util\CdnUrlGenerator;
use Orbit\Helper\Util\LandingPageUrlGenerator;
use Media;
use BaseStore;
use BaseMerchant;
use Coupon;
use Str;

/**
 * Notify recipient to accept or decline a coupon transfer.
 *
 * @author Budi <budi@dominopos.com>
 */
class ConfirmTransferNotification extends CouponTransferNotification
{
    protected $signature = 'confirm-transfer';

    protected $logID = 'ConfirmTransferNotification';

    /**
     * Get the email templates that will be used.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.coupon.transfer.confirm-transfer',
            'text' => 'emails.coupon.transfer.confirm-transfer-text',
        ];
    }

    private function getImageUrl($couponId)
    {

        $img = Media::select('path', 'cdn_url')
            ->where('object_id', $couponId)
            ->where('object_name', 'coupon')
            ->where('media_name_id', 'coupon_image')
            ->where('media_name_long', 'coupon_image_resized_default')
            ->first();
        if (!empty($img)) {
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
            return $imgUrl->getImageUrl($img->path, $img->cdn_url);
        } else {
            $baseLandingPageUrl = Config::get('orbit.base_landing_page_url', 'https://gotomalls.com');
            return $baseLandingPageUrl . '/themes/default/images/campaign-default.png';
        }
    }

    private function getBrand($couponId)
    {
        $coupon = Coupon::find($couponId);
        $baseMerchant = $coupon->linkToTenants()
            ->baseStore()
            ->baseMerchant();
        $names = $baseMerchant->select('name')
            ->groupBy('base_merchant_id')
            ->get()
            ->list('name');
        return join($names, ',');
    }

    /**
     * Generate coupon detail url.
     *
     * @return [type] [description]
     */
    private function getCouponUrl($couponId, $couponName)
    {
        $baseLandingPageUrl = Config::get('orbit.base_landing_page_url', 'https://gotomalls.com');
        return $baseLandingPageUrl . "/coupon/{$couponId}/" . Str::slug($couponName);
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        $coupon = $this->issuedCoupon->coupon;
        return array_merge(parent::getEmailData(), [
            'header'            => trans('email-transfer.header'),
            'greeting'          => trans('email-transfer.confirm.greeting', ['recipientName' => $this->recipientName]),
            'emailSubject'      => trans('email-transfer.confirm.subject', ['ownerName' => $this->issuedCoupon->user->getFullName()]),
            'body'              => trans('email-transfer.confirm.message', ['ownerName' => $this->issuedCoupon->user->getFullName()]),
            'couponId'          => $coupon->promotion_id,
            'couponName'        => $coupon->promotion_name,
            'couponUrl'         => $this->getCouponUrl($coupon->promotion_id, $coupon->promotion_name),
            'couponImage'       => $this->getImageUrl($coupon->promotion_id),
            'brandName'         => $this->getBrand($coupon->promotion_id),
            'acceptUrl'         => $this->generateAcceptUrl(),
            'btnAccept'         => trans('email-transfer.confirm.btn_accept'),
            'declineUrl'        => $this->generateDeclineUrl(),
            'btnDecline'        => trans('email-transfer.confirm.btn_decline'),
        ]);
    }

    /**
     * Generate url for accepting coupon transfer.
     *
     * @return [type] [description]
     */
    private function generateAcceptUrl()
    {
        $baseLandingPageUrl = Config::get('orbit.base_landing_page_url', 'https://gotomalls.com');

        return sprintf(
            "%s/coupons/accept-transfer?issuedCouponId=%s&email=%s",
            $baseLandingPageUrl,
            $this->issuedCoupon->issued_coupon_id,
            $this->issuedCoupon->transfer_email
        );
    }

    /**
     * Generate url for declining coupon transfer.
     *
     * @return [type] [description]
     */
    private function generateDeclineUrl()
    {
        $baseLandingPageUrl = Config::get('orbit.base_landing_page_url', 'https://gotomalls.com');

        return sprintf(
            "%s/coupons/decline-transfer?issuedCouponId=%s&email=%s",
            $baseLandingPageUrl,
            $this->issuedCoupon->issued_coupon_id,
            $this->issuedCoupon->transfer_email
        );
    }
}
