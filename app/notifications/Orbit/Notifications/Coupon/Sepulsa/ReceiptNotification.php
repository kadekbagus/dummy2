<?php namespace Orbit\Notifications\Coupon\Sepulsa;

use Orbit\Helper\Notifications\Notification;
use Orbit\Helper\Util\JobBurier;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\Util\LandingPageUrlGenerator as LandingPageUrlGenerator;
use Orbit\Helper\Util\CdnUrlGenerator;
use Carbon\Carbon;

use DB;
use Mail;
use Config;
use Log;
use Queue;
use Exception;
use Coupon;

/**
 * Notify user after completing and getting Sepulsa Voucher.
 *
 */
class ReceiptNotification extends Notification
{
    protected $payment = null;

    protected $contact = null;

    protected $mongoConfig = null;

    function __construct($payment = null)
    {
        $this->payment      = $payment;
        $this->queueName    = Config::get('orbit.registration.mobile.queue_name');
        $this->contact      = Config::get('orbit.contact_information');
        $this->mongoConfig  = Config::get('database.mongodb');
    }

    /**
     * Custom field email address if not reading from field 'email'
     *
     * @return [type] [description]
     */
    protected function getEmailAddress()
    {
        return $this->payment->user_email;
    }

    /**
     * Custom name if not reading from field 'name'.
     *
     * @return [type] [description]
     */
    protected function getName()
    {
        return $this->payment->user_name;
    }

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    protected function getEmailData()
    {
        $transaction = [];

        $amount = $this->payment->getAmount();

        $transaction['id']    = $this->payment->payment_transaction_id;
        $transaction['date']  = Carbon::parse($this->payment->transaction_date_and_time)->format('j M Y');
        $transaction['total'] = $amount;
        $redeemUrl            = Config::get('orbit.coupon.direct_redemption_url');
        $cs = [
            'phone' => $this->contact['customer_service']['phone'],
            'email' => $this->contact['customer_service']['email'],
        ];

        $transaction['items'] = [
            [
                'name'      => $this->payment->object_name,
                'quantity'  => 1,
                'price'     => $amount,
                'total'     => $amount, // should be quantity * $this->payment->amount
            ],
        ];

        return [
            'customerEmail'     => $this->getEmailAddress(),
            'customerName'      => $this->getName(),
            'customerPhone'     => $this->payment->phone,
            'transaction'       => $transaction,
            'redeemUrl'         => $redeemUrl,
            'cs'                => $cs,
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

            $emailTemplate = 'emails.receipt.sepulsa';

            Mail::send($emailTemplate, $data, function($mail) use ($data) {
                $emailConfig = Config::get('orbit.registration.mobile.sender');

                $subject = 'Your Receipt from Gotomalls.com';

                $mail->subject($subject);
                $mail->from($emailConfig['email'], $emailConfig['name']);
                $mail->to($data['customerEmail']);
            });

            $job->delete();

            // Bury the job for later inspection
            // JobBurier::create($job, function($theJob) {
            //     // The queue driver does not support bury.
            //     $theJob->delete();
            // })->bury();

        } catch (Exception $e) {
            Log::debug('Notification: ReceiptNotification email exception. Line:' . $e->getLine() . ', Message: ' . $e->getMessage());
        }
    }

    /**
     * Notify to web / user's notification.
     * MongoDB record, appear in user's notifications list page
     *
     * @return [type] [description]
     */
    public function toWeb($bodyInApps)
    {
        if (!empty($bodyInApps)) {
            $mongoClient = MongoClient::create($this->mongoConfig);
            $inApps = $mongoClient->setFormParam($bodyInApps)
                                  ->setEndPoint('user-notifications')
                                  ->request('POST');
        }
    }

    /**
     * Send notification.
     *
     * @return [type] [description]
     */
    public function send()
    {
        Queue::later(
            3,
            'Orbit\\Notifications\\Coupon\\Sepulsa\\ReceiptNotification@toEmail',
            $this->getEmailData(),
            $this->queueName
        );

        // Other notification method can be added here...
        $bodyInApps = $this->getInAppData();
        $this->toWeb($bodyInApps);
    }

    public function getInAppData()
    {
        $bodyInApps = null;
        $userId = $this->payment->user_id;
        $couponId = $this->payment->object_id;
        $prefix = DB::getTablePrefix();
        $coupon = Coupon::select(DB::raw("{$prefix}promotions.promotion_id,
                                    {$prefix}promotions.promotion_name,
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
            $launchUrl = LandingPageUrlGenerator::create('coupon', $coupon->promotion_id, $coupon->promotion_name)->generateUrl(true);

            $headings = new \stdClass();
            $headings->en = $coupon->promotion_name;
            $contents = new \stdClass();
            $contents->en = 'Your voucher is ready! Click here to redeem your voucher';

            $notificationData = new \stdClass();
            $notificationData->title = $coupon->promotion_name;
            $notificationData->launch_url = $launchUrl;
            $notificationData->default_language = 'en';
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
                'user_id'       => $userId,
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
}
