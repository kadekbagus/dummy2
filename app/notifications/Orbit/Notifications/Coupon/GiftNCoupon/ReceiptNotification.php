<?php namespace Orbit\Notifications\Coupon\GiftNCoupon;

use Orbit\Notifications\Coupon\HotDeals\ReceiptNotification as BaseReceiptNotification;

/**
 * Receipt Notification for Customer after purchasing Gift N Coupon (Paid Coupon).
 *
 * @todo  delete this notification class since we use one template
 *        for both sepulsa and hot deals.
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
        return 'https://s3-ap-southeast-1.amazonaws.com/asset1.gotomalls.com/uploads/coupon/translation/2019/03/M8ppHct5IFcTbepD--1553823050_1.jpg';
    }
}
