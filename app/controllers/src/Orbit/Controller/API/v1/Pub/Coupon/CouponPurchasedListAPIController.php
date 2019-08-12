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

class CouponPurchasedListAPIController extends PubControllerAPI
{
    private function calculateCount($prefix, $user)
    {
        $transact = PaymentTransaction::select(DB::raw("
                                COUNT(DISTINCT {$prefix}payment_transactions.payment_transaction_id) AS tot
                        "))

                        ->join('payment_transaction_details', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                        ->join('promotions', 'promotions.promotion_id', '=', 'payment_transaction_details.object_id')
                        ->where('payment_transactions.user_id', $user->user_id)
                        ->where('payment_transaction_details.object_type', 'coupon')
                        ->where('payment_transactions.payment_method', '!=', 'normal')
                        ->whereNotIn('payment_transactions.status', array('starting', 'denied'))
                        ->take(1);
        return (int) $transact->first()->tot;
    }

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
    public function getCouponPurchasedList()
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

            $sort_by = OrbitInput::get('sortby', 'coupon_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();
            $validator = Validator::make(
                array(
                    'language' => $language,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
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
                                    {$prefix}payment_transaction_details.object_id as object_id,
                                    {$prefix}payment_transactions.amount,
                                    {$prefix}payment_transactions.currency,
                                    CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as coupon_name,
                                    CONCAT({$prefix}payment_normal_paypro_details.store_name,' @ ', {$prefix}payment_normal_paypro_details.building_name) as store_at_building,
                                    {$prefix}payment_transactions.payment_transaction_id,
                                    {$prefix}payment_transactions.created_at,
                                    convert_tz( {$prefix}payment_transactions.created_at, '+00:00', {$prefix}payment_transactions.timezone_name) as date_tz,
                                    {$prefix}payment_transactions.status,
                                    {$prefix}payment_transactions.payment_method,
                                    CASE WHEN ({$prefix}coupon_translations.description = '' or {$prefix}coupon_translations.description is null) THEN default_translation.description ELSE {$prefix}coupon_translations.description END as description,

                                    CASE WHEN {$prefix}media.path is null THEN (
                                        SELECT
                                          m.path
                                        FROM {$prefix}coupon_translations AS ct
                                        JOIN {$prefix}media AS m
                                        ON m.object_id = ct.coupon_translation_id AND m.media_name_long = 'coupon_translation_image_orig'
                                        WHERE ct.promotion_id = {$prefix}promotions.promotion_id
                                        LIMIT 1
                                    ) ELSE {$prefix}media.path END as localPath,

                                    CASE WHEN {$prefix}media.cdn_url is null THEN (
                                        SELECT
                                          m.cdn_url
                                        FROM {$prefix}coupon_translations AS ct
                                        JOIN {$prefix}media AS m
                                        ON m.object_id = ct.coupon_translation_id AND m.media_name_long = 'coupon_translation_image_orig'
                                        WHERE ct.promotion_id = {$prefix}promotions.promotion_id
                                        LIMIT 1
                                    ) ELSE {$prefix}media.cdn_url END as cdnPath,

                                    (SELECT substring_index(group_concat(distinct om.name SEPARATOR ', '), ', ', 2)
                                                    FROM {$prefix}promotion_retailer opr
                                                    JOIN {$prefix}merchants om
                                                        ON om.merchant_id = opr.retailer_id
                                                    WHERE opr.promotion_id = {$prefix}promotions.promotion_id
                                                    GROUP BY opr.promotion_id
                                                    ORDER BY om.name
                                                ) as link_to_tenant,
                                    (SELECT D.value_in_percent FROM {$prefix}payment_transaction_details PTD
                                        LEFT JOIN {$prefix}discount_codes DC on DC.discount_code_id = PTD.object_id
                                        LEFT JOIN {$prefix}discounts D on D.discount_id = DC.discount_id
                                        WHERE PTD.object_type = 'discount'
                                        AND PTD.payment_transaction_id = {$prefix}payment_transactions.payment_transaction_id) as discount_percent,
                                    (SELECT PTD.price FROM {$prefix}payment_transaction_details PTD
                                        WHERE PTD.object_type = 'discount'
                                        AND PTD.payment_transaction_id = {$prefix}payment_transactions.payment_transaction_id) as discount_amount
                            "))

                            ->join('payment_transaction_details', 'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id')
                            ->join('promotions', 'promotions.promotion_id', '=', 'payment_transaction_details.object_id')
                            ->join('merchants', function ($q) {
                                $q->on('merchants.merchant_id', '=', 'promotions.merchant_id');
                            })
                            ->join('merchants as malls', function ($q) {
                                $q->on('merchants.parent_id', '=', DB::raw("malls.merchant_id"));
                            })
                            ->leftJoin('payment_normal_paypro_details', 'payment_normal_paypro_details.payment_transaction_detail_id', '=', 'payment_transaction_details.payment_transaction_detail_id')

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
                            ->leftJoin('timezones', function ($q) use($prefix) {
                                $q->on('timezones.timezone_id', '=', DB::raw("CASE WHEN {$prefix}merchants.object_type = 'mall' THEN {$prefix}merchants.timezone_id ELSE malls.timezone_id END"));
                            })
                            ->where('payment_transactions.user_id', $user->user_id)
                            ->where('payment_transaction_details.object_type', 'coupon')
                            ->where('payment_transactions.payment_method', '!=', 'normal')
                            ->whereNotIn('payment_transactions.status', array('starting', 'denied', 'abort'))
                            ->groupBy('payment_transactions.payment_transaction_id');

            OrbitInput::get('filter_name', function ($filterName) use ($coupon, $prefix) {
                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $coupon->whereRaw("SUBSTR({$prefix}coupon_translations.promotion_name,1,1) not between 'a' and 'z'");
                    } else {
                        $filter = explode("-", $filterName);
                        $coupon->whereRaw("SUBSTR({$prefix}coupon_translations.promotion_name,1,1) between {$this->quote($filter[0])} and {$this->quote($filter[1])}");
                    }
                }
            });

            OrbitInput::get('mall_id', function ($mallId) use ($coupon, $prefix, &$mall) {
                $coupon->addSelect(DB::raw("CASE WHEN t.object_type = 'tenant' THEN t.parent_id ELSE t.merchant_id END as mall_id"));
                $coupon->addSelect(DB::raw("CASE WHEN t.object_type = 'tenant' THEN m.name ELSE t.name END as mall_name"));
                $coupon->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                    ->leftJoin('merchants as t', DB::raw("t.merchant_id"), '=', 'promotion_retailer.retailer_id')
                    ->leftJoin('merchants as m', DB::raw("m.merchant_id"), '=', DB::raw("t.parent_id"));
                $coupon->where(function($q) use ($mallId) {
                    $q->where(DB::raw('t.merchant_id'), '=', DB::raw("{$this->quote($mallId)}"));
                    $q->orWhere(DB::raw('m.merchant_id'), '=', DB::raw("{$this->quote($mallId)}"));
                });

                $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $mallId)
                        ->first();
            });

            $coupon = $coupon->orderBy(DB::raw("{$prefix}payment_transactions.created_at"), 'desc');

            $_coupon = clone $coupon;

            $take = PaginationNumber::parseTakeFromGet('coupon');
            $coupon->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $coupon->skip($skip);

            $listcoupon = $coupon->get();
            //$count = RecordCounter::create($_coupon)->count();
            $count = $this->calculateCount($prefix, $user);

            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
            $localPath = '';
            $cdnPath = '';
            $listId = '';

            if (count($listcoupon) > 0) {
                foreach ($listcoupon as $list) {
                    if ($listId != $list->promotion_id) {
                        $localPath = '';
                        $cdnPath = '';
                        $list->image_url = '';
                    }
                    $localPath = (! empty($list->localPath)) ? $list->localPath : '';
                    $cdnPath = (! empty($list->cdnPath)) ? $list->cdnPath : '';
                    $list->original_media_path = $imgUrl->getImageUrl($localPath, $cdnPath);
                    $listId = $list->promotion_id;
                }
            }

            if (empty($skip)) {
                $activityNotes = sprintf('Page viewed: Landing Page Coupon Wallet List Page');
                $activity->setUser($user)
                    ->setActivityName('view_landing_page_coupon_wallet_list')
                    ->setActivityNameLong('View GoToMalls Coupon Wallet List')
                    ->setObject(NULL)
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listcoupon);
            $this->response->data->records = $listcoupon;
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
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
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
