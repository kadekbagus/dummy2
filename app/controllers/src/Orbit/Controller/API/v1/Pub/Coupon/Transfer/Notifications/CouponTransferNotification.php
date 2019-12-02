<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer\Notifications;

use Orbit\Helper\Notifications\Contracts\EmailNotificationInterface;
use Orbit\Helper\Notifications\CustomerNotification;
use Orbit\Notifications\Traits\HasContactTrait;
use Orbit\Helper\Util\CdnUrlGenerator;
use Orbit\Helper\Util\LandingPageUrlGenerator;
use Exception;
use Log;
use Mail;
use Config;
use Media;
use BaseStore;
use BaseMerchant;
use Coupon;
use DB;
use Str;

/**
 * Base coupon transfer notification.
 *
 * @author Budi <budi@dominopos.com>
 */
class CouponTransferNotification extends CustomerNotification implements EmailNotificationInterface
{
    use HasContactTrait;

    protected $issuedCoupon;

    /**
     * Indicate if we should push this job to queue.
     * @var boolean
     */
    protected $shouldQueue = true;

    /**
     * @var integer
     */
    protected $notificationDelay = 3;

    protected $context = 'coupon-transfer';

    protected $recipientName = '';

    function __construct($issuedCoupon = null, $recipientName = '')
    {
        $this->issuedCoupon = $issuedCoupon;
        $this->recipientName = $recipientName;
    }

    public function getRecipientEmail()
    {
        return $this->notifiable->email;
    }

    public function getEmailTemplates()
    {
        return [];
    }

    public function getEmailData()
    {
        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'cs'                => $this->getContactData(),
            'templates'         => $this->getEmailTemplates(),
        ];
    }

    protected function getImageUrl($couponId)
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

    protected function getBrand($couponId)
    {
        $names = DB::table('promotion_retailer')
            ->join('base_stores', 'base_stores.base_store_id', '=', 'promotion_retailer.retailer_id')
            ->join('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
            ->select('base_merchants.name')
            ->where('promotion_retailer.promotion_id', $couponId)
            ->groupBy('base_merchants.base_merchant_id')
            ->lists('name');
        return join(',', $names);
    }

    /**
     * Generate coupon detail url.
     *
     * @return [type] [description]
     */
    protected function getCouponUrl($couponId, $couponName)
    {
        $baseLandingPageUrl = Config::get('orbit.base_landing_page_url', 'https://gotomalls.com');
        return $baseLandingPageUrl . "/coupons/{$couponId}/" . Str::slug($couponName);
    }

    /**
     * Notify via email.
     * This method act as custom Queue handler.
     *
     * @param  [type] $notifiable [description]
     * @return [type]             [description]
     */
    public function toEmail($job, $data)
    {
        try {
            Mail::send($data['templates'], $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = $data['emailSubject'];

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

        } catch (Exception $e) {
            Log::info($this->logID . ': email exception. File: ' . $e->getFile() . ', Lines:' . $e->getLine() . ', Message: ' . $e->getMessage());
            Log::info($this->logID . ': email data: ' . serialize($data));
        }

        $job->delete();
    }

}
