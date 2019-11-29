<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Notifications;

use Illuminate\Support\Facades\Config;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Notifications\CouponTransferNotification;
use Orbit\Helper\Util\CdnUrlGenerator;
use Orbit\Helper\Util\LandingPageUrlGenerator;
use Media;

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

    private function getImageUrl($coupon)
    {
        return 'https://img.juhara.com/100x100.png';

        // $img = Media::select('path', 'cdn_url')
        //     ->where('object_id', $coupon->promotion_id)
        //     ->where('object_name', 'coupon')
        //     ->where('media_name_id', 'coupon_image')
        //     ->where('media_name_long', 'coupon_image_resized_default')
        //     ->first();
        // if (empty($img)) {
        //     $cdnConfig = Config::get('orbit.cdn');
        //     $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
        //     return $imgUrl->getImageUrl($img->path, $img->cdn_url);
        // } else {
        //     $baseLandingPageUrl = Config::get('orbit.base_landing_page_url', 'https://gotomalls.com');
        //     return $baseLandingPageUrl + '/themes/default/images/campaign-default.png';
        // }
    }

    /**
     * Generate coupon detail url.
     *
     * @return [type] [description]
     */
    private function getCouponUrl($coupon)
    {
        return LandingPageUrlGenerator::create(
            'coupon',
            $coupon->promotion_id,
            $coupon->promotion_name
        )->generateUrl(true);
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        $brandName = '';
        $coupon = $this->issuedCoupon->coupon;
        return array_merge(parent::getEmailData(), [
            'header'            => trans('email-transfer.header'),
            'greeting'          => trans('email-transfer.confirm.greeting', ['recipientName' => $this->recipientName]),
            'emailSubject'      => trans('email-transfer.confirm.subject', ['ownerName' => $this->issuedCoupon->user->getFullName()]),
            'body'              => trans('email-transfer.confirm.message', ['ownerName' => $this->issuedCoupon->user->getFullName()]),
            'coupon' => $coupon,
            'couponName'        => $coupon->promotion_name,
            'couponUrl'         => $this->getCouponUrl($coupon),
            'couponImage'       => $this->getImageUrl($coupon),
            'brandName'         => $brandName,
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
