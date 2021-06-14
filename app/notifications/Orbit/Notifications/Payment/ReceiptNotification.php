<?php namespace Orbit\Notifications\Payment;

use DB;
use Mail;
use Config;
use Log;
use Queue;
use Exception;
use Coupon;
use PromotionRetailer;
use CouponTranslation;

use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;
use Orbit\Helper\Util\CdnUrlGenerator;
use Carbon\Carbon;

use Orbit\Helper\Notifications\CustomerNotification;
use Orbit\Helper\Notifications\Contracts\EmailNotificationInterface;
use Orbit\Helper\Notifications\Contracts\InAppNotificationInterface;

use Orbit\Notifications\Traits\HasPaymentTrait;
use Orbit\Notifications\Traits\HasContactTrait;

/**
 * Base Receipt Notification class.
 *
 * @author Budi <budi@dominopos.com>
 */
class ReceiptNotification extends CustomerNotification implements
    EmailNotificationInterface,
    InAppNotificationInterface
{
    use HasPaymentTrait, HasContactTrait, HasPurchaseRewards;

    protected $shouldQueue = true;

    protected $context = 'transaction';

    protected $signature = 'receipt-notification';

    function __construct($payment = null)
    {
        $this->payment = $payment;
    }

    /**
     * @override
     * @return [type] [description]
     */
    protected function notificationMethods()
    {
        // Set to notify via email and InApp
        return ['email', 'inApp'];
    }

    public function getRecipientEmail()
    {
        return $this->getCustomerEmail();
    }

    /**
     * Get the email templates.
     * At the moment we can use same template for both Sepulsa and Hot Deals.
     * Can be overriden in each receipt class if needed.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.hot-deals.receipt',
        ];
    }

    /**
     * Get email subject.
     * @return [type] [description]
     */
    protected function getEmailSubject()
    {
        return trans('email-receipt.subject', [], '', 'id');
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        return [
            'recipientEmail'    => $this->getRecipientEmail(),
            'customerEmail'     => $this->getCustomerEmail(),
            'customerName'      => $this->getCustomerName(),
            'customerPhone'     => $this->getCustomerPhone(),
            'transaction'       => $this->getTransactionData(),
            'cs'                => $this->getContactData(),
            'redeemUrl'         => Config::get('orbit.coupon.direct_redemption_url'),
            'transactionDateTime' => $this->payment->getTransactionDate('d F Y, H:i ') . " {$this->getLocalTimezoneName($this->payment->timezone_name)}",
            'emailSubject'      => $this->getEmailSubject(),
            'gameName'          => $this->getGameName(),
            'purchaseRewards'   => $this->getPurchaseRewards(),
        ];
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
            Mail::send($this->getEmailTemplates(), $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = $data['emailSubject'];

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['recipientEmail']);
            });

        } catch (Exception $e) {
            Log::debug('Notification: ReceiptNotification email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
        }

        $job->delete();
    }

    /**
     * Get InApp notification data.
     *
     * @return [type] [description]
     */
    public function getInAppData()
    {
        $bodyInApps = null;
        $userId = $this->payment->user_id;
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
            $couponTranslations = CouponTranslation::select(DB::raw("{$prefix}languages.name as lang, {$prefix}coupon_translations.promotion_name"))
                                                    ->join('languages', 'coupon_translations.merchant_language_id', '=', 'languages.language_id')
                                                    ->where('promotion_id', $couponId)
                                                    ->get();

            //$launchUrl = LandingPageUrlGenerator::create('coupon', $coupon->promotion_id, $coupon->promotion_name)->generateUrl(true);
            $couponCountry = PromotionRetailer::select(DB::raw("malls.country"))
                            ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                            ->join('merchants as malls', function ($q) {
                                $q->on('merchants.parent_id', '=', DB::raw("malls.merchant_id"));
                            })
                            ->where('promotion_retailer.promotion_id', '=', $couponId)
                            ->first();

            $launchUrl = '/my/coupons?country='.$couponCountry->country;

            $headings = new \stdClass();
            $headings->{$coupon->mobile_default_language} = $coupon->promotion_name;
            foreach($couponTranslations as $couponTranslation) {
                if (empty($couponTranslation->promotion_name)) {
                    $headings->{$couponTranslation->lang} = $coupon->promotion_name;
                }
                else {
                    $headings->{$couponTranslation->lang} = $couponTranslation->promotion_name;
                }
            }

            $contents = new \stdClass();
            $contents->en = 'Your voucher is ready! Click here to redeem';
            $contents->id = 'Voucher Anda sudah siap! Klik di sini untuk menukar';

            $notificationData = new \stdClass();
            $notificationData->title = $coupon->promotion_name;
            $notificationData->launch_url = $launchUrl;
            $notificationData->default_language = $coupon->mobile_default_language;
            $notificationData->headings = $headings;
            $notificationData->contents = $contents;
            $notificationData->type = 'coupon';

            $timezone = 'Asia/Makassar';
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->toDateTimeString();

            $attachmentPath = (!empty($coupon->localPath)) ? $coupon->localPath : '';
            $cdnUrl = (!empty($coupon->cdnPath)) ? $coupon->cdnPath : '';
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
            $imageUrl = $imgUrl->getImageUrl($attachmentPath, $cdnUrl);

            $bodyInApps = [
                'user_ids'      => [$userId],
                'token'         => null,
                'notifications' => $notificationData,
                'send_status'   => 'sent',
                'is_viewed'     => false,
                'is_read'       => false,
                'created_at'    => $dateTime,
                'image_url'     => $imageUrl
            ];
        }

        return $bodyInApps;
    }

    /**
     * Notify to web / user's notification.
     * MongoDB record, appear in user's notifications list page
     *
     * @return [type] [description]
     */
    public function toWeb($job, $data)
    {
        try {
            $mongoClient = MongoClient::create(Config::get('database.mongodb'));
            $inApps = $mongoClient->setFormParam($data)
                                  ->setEndPoint('user-notifications')
                                  ->request('POST');

        } catch (Exception $e) {
            Log::debug('Notification: ReceiptNotification inApp exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
        }

        $job->delete();
    }

}
