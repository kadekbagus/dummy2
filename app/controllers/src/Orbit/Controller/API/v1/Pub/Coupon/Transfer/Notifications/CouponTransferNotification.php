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
        $couponImage = '';
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

        // Default fallback image.
        if (empty($couponImage)) {
            $baseLandingPageUrl = Config::get('orbit.base_landing_page_url', 'https://gotomalls.com');
            $couponImage = $baseLandingPageUrl . '/themes/default/images/campaign-default.png';
        }

        return $couponImage;
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

        $brandNames = join(',', $names);

        if (count($names) > 3) {
            $otherBrandCount = count($names) - 3;
            $brandNames = join(', ', array_splice($names, 3)) . " and {$otherBrandCount} other.";
        }

        return $brandNames;
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
