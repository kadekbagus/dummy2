<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use Coupon;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Activity;
use Mall;
use Lang;
use \Exception;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Orbit\Helper\Util\CdnUrlGenerator;
use PromotionRetailer;
use PaymentTransaction;
use Helper\EloquentRecordCounter as RecordCounter;

class CouponPurchasedDetailAPIController extends PubControllerAPI
{
    /**
     * GET - get all coupon wallet in all mall
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCouponPurchasedDetail()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $mall = NULL;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $language = OrbitInput::get('language', 'id');
            $payment_transaction_id = OrbitInput::get('payment_transaction_id');

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();
            $validator = Validator::make(
                array(
                    'language'               => $language,
                    'payment_transaction_id' => $payment_transaction_id
                ),
                array(
                    'language'               => 'required|orbit.empty.language_default',
                    'payment_transaction_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $couponHelper->getValidLanguage();

            $prefix = DB::getTablePrefix();

            $coupon = PaymentTransaction::select(DB::raw("
                                    {$prefix}payment_transactions.payment_transaction_id,
                                    {$prefix}payment_transactions.external_payment_transaction_id,
                                    {$prefix}payment_transactions.user_name,
                                    {$prefix}payment_transactions.user_email,
                                    {$prefix}payment_transactions.currency,
                                    {$prefix}payment_transactions.amount,
                                    {$prefix}promotions.price_selling,
                                    FORMAT({$prefix}payment_transactions.amount / {$prefix}promotions.price_selling, 0) as qty,
                                    {$prefix}payment_transactions.status,
                                    {$prefix}payment_midtrans.payment_midtrans_info,
                                    {$prefix}promotions.promotion_id  as coupon_id,
                                    {$prefix}promotions.promotion_type  as coupon_type,
                                    CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as coupon_name,
                                    {$prefix}payment_transactions.created_at,
                                    convert_tz( {$prefix}payment_transactions.created_at, '+00:00', {$prefix}payment_transactions.timezone_name) as date_tz,
                                    {$prefix}payment_transactions.payment_method,
                                    CASE WHEN {$prefix}media.path is null THEN med.path ELSE {$prefix}media.path END as localPath,
                                    CASE WHEN {$prefix}media.cdn_url is null THEN med.cdn_url ELSE {$prefix}media.cdn_url END as cdnPath,
                                    (SELECT substring_index(group_concat(distinct om.name SEPARATOR ', '), ', ', 2)
                                                    FROM {$prefix}promotion_retailer opr
                                                    JOIN {$prefix}merchants om
                                                        ON om.merchant_id = opr.retailer_id
                                                    WHERE opr.promotion_id = {$prefix}promotions.promotion_id
                                                    GROUP BY opr.promotion_id
                                                    ORDER BY om.name
                                                ) as link_to_tenant
                            "))

                            ->leftJoin('payment_transaction_details', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                            ->leftJoin('payment_midtrans', 'payment_midtrans.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                            ->join('promotions', 'promotions.promotion_id', '=', 'payment_transaction_details.object_id')
                            ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                            ->join('languages as default_languages', DB::raw('default_languages.name'), '=', 'campaign_account.mobile_default_language')
                            ->leftJoin('coupon_translations', function ($q) use ($valid_language) {
                                $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                                  ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                            })
                            ->leftJoin('coupon_translations as default_translation', function ($q) {
                                $q->on(DB::raw('default_translation.promotion_id'), '=', 'promotions.promotion_id')
                                  ->on(DB::raw('default_translation.merchant_language_id'), '=', DB::raw('default_languages.language_id'));
                            })
                            ->leftJoin('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id')
                            ->leftJoin('media', function ($q) {
                                $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                                $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                            })
                            ->leftJoin('issued_coupons', function ($join) {
                                $join->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                                $join->where('issued_coupons.status', '!=', 'deleted');
                            })
                            ->leftJoin('merchants', function ($q) {
                                $q->on('merchants.merchant_id', '=', 'issued_coupons.redeem_retailer_id');
                            })
                            ->leftJoin('merchants as malls', function ($q) {
                                $q->on('merchants.parent_id', '=', DB::raw("malls.merchant_id"));
                            })
                            ->leftJoin('timezones', function ($q) use($prefix) {
                                $q->on('timezones.timezone_id', '=', DB::raw("CASE WHEN {$prefix}merchants.object_type = 'mall' THEN {$prefix}merchants.timezone_id ELSE malls.timezone_id END"));
                            })
                            ->leftJoin(DB::raw("(SELECT m.path, m.cdn_url, ct.promotion_id
                                        FROM {$prefix}coupon_translations ct
                                        JOIN {$prefix}media m
                                            ON m.object_id = ct.coupon_translation_id
                                            AND m.media_name_long = 'coupon_translation_image_orig'
                                        GROUP BY ct.promotion_id) AS med"), DB::raw("med.promotion_id"), '=', 'promotions.promotion_id')
                            ->where('payment_transactions.user_id', $user->user_id)
                            ->where('payment_transaction_details.object_type', 'coupon')
                            ->where('payment_transactions.payment_method', '!=', 'normal')

                            // payment_transaction_id is value of payment_transaction_id or external_payment_transaction_id
                            ->where(function($query) use($payment_transaction_id) {
                                $query->where('payment_transactions.payment_transaction_id', '=', $payment_transaction_id)
                                      ->orWhere('payment_transactions.external_payment_transaction_id', '=', $payment_transaction_id);
                              })
                            ->first();

            if (!$coupon) {
                OrbitShopAPI::throwInvalidArgument('purchased detail not found');
            }

            $coupon->redeem_codes = null;
            if ($coupon->coupon_type === 'gift_n_coupon') {
                $coupon->redeem_codes = PaymentTransaction::select('issued_coupons.url')
                    ->join('issued_coupons', function ($q) {
                        $q->on('issued_coupons.transaction_id', '=', 'payment_transactions.payment_transaction_id');
                    })
                    // payment_transaction_id is value of payment_transaction_id or external_payment_transaction_id
                    ->where(function($query) use($payment_transaction_id) {
                        $query->where('payment_transactions.payment_transaction_id', '=', $payment_transaction_id)
                              ->orWhere('payment_transactions.external_payment_transaction_id', '=', $payment_transaction_id);
                      })
                    ->where('issued_coupons.status', '=', 'issued')
                    ->get()
                    ->lists('issued_coupon_code');
            }

            // Fallback to IDR by default?
            if (empty($coupon->currency)) {
                $coupon->currency = 'IDR';
            }

            // if ($coupon->currency === 'IDR') {
            //     $coupon->price_selling = number_format($coupon->price_selling, 0, ',', '.');
            //     $coupon->amount = number_format($coupon->amount, 0, ',', '.');
            // }
            // else {
            //     $coupon->price_selling = number_format($coupon->price_selling, 2, '.', ',');
            //     $coupon->amount = number_format($coupon->amount, 2, '.', ',');
            // }

            // get Imahe from local when image cdn is null
            if ($coupon->cdnPath == null) {
                $cdnConfig = Config::get('orbit.cdn');
                $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
                $localPath = (! empty($coupon->localPath)) ? $coupon->localPath : '';
                $cdnPath = (! empty($coupon->cdnPath)) ? $coupon->cdnPath : '';
                $coupon->cdnPath = $imgUrl->getImageUrl($localPath, $cdnPath);
            }

            $coupon->payment_midtrans_info = json_decode(unserialize($coupon->payment_midtrans_info));

            $this->response->data = $coupon;
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
