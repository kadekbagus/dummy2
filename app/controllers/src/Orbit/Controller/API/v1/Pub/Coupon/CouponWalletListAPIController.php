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
use IssuedCoupon;
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
use Helper\EloquentRecordCounter as RecordCounter;

class CouponWalletListAPIController extends PubControllerAPI
{
    /**
     * calculate aggregate for limited number of coupons.
     * Note: instead of directly join with promotions table
     * we only do aggreates on very limited amount of coupons
     * @param  array $coupons [description]
     * @return [type]          [description]
     */
    private function getTotalIssuedAndRedeemed($coupons)
    {
        $couponIds = array_map(function($coupon) {
            return $coupon->promotion_id;
        }, $coupons);

        $prefix = DB::getTablePrefix();
        $issuedCoupons = IssuedCoupon::select(DB::raw("
            {$prefix}issued_coupons.promotion_id,
            COUNT({$prefix}issued_coupons.issued_coupon_id) AS total_issued,
            SUM({$prefix}issued_coupons.status = 'redeemed') AS total_redeemed
        "))
        ->whereIn("{$prefix}issued_coupons.promotion_id", $couponIds)
        ->whereIn("{$prefix}issued_coupons.status", array('issued', 'redeemed'))
        ->groupBy("{$prefix}issued_coupons.promotion_id")
        ->get();

        $couponStats = array_map(function($stat) {
            return array(
                $stat->promotion_id => array(
                    'total_issued' => $stat->total_issued,
                    'total_redeemed' => $stat->total_redeemed
                )
            );
        }, $issuedCoupons);

        return array_map(function($coupon) use($couponStats) {
            $coupon->total_issued = $couponStats[$coupon->promotion_id]['total_issued'];
            $coupon->total_redeemed = $couponStats[$coupon->promotion_id]['total_redeemed'];
            return $coupon;
        }, $coupons);
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
    public function getCouponWalletList()
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

            $coupon = Coupon::select(DB::raw("
                                    {$prefix}promotions.promotion_id as promotion_id,
                                    CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as coupon_name,
                                    CASE WHEN ({$prefix}coupon_translations.description = '' or {$prefix}coupon_translations.description is null) THEN default_translation.description ELSE {$prefix}coupon_translations.description END as description,

                                    CASE WHEN {$prefix}media.path is null THEN (
                                        SELECT m.path
                                        FROM {$prefix}coupon_translations ct
                                        INNER JOIN {$prefix}media m
                                        ON m.object_id = ct.coupon_translation_id
                                        AND m.media_name_long = 'coupon_translation_image_orig'
                                        WHERE ct.promotion_id = {$prefix}promotions.promotion_id
                                        LIMIT 1
                                    ) ELSE {$prefix}media.path END as localPath,

                                    CASE WHEN {$prefix}promotions.promotion_type = 'sepulsa'
                                        THEN {$prefix}coupon_sepulsa.coupon_image_url
                                        ELSE (
                                            CASE WHEN {$prefix}media.cdn_url is null
                                            THEN (
                                                SELECT m.cdn_url
                                                FROM {$prefix}coupon_translations ct
                                                INNER JOIN {$prefix}media m
                                                ON m.object_id = ct.coupon_translation_id
                                                AND m.media_name_long = 'coupon_translation_image_orig'
                                                WHERE ct.promotion_id = {$prefix}promotions.promotion_id
                                                LIMIT 1
                                            )
                                            ELSE {$prefix}media.cdn_url
                                            END
                                        )
                                        END as cdnPath,
                                    {$prefix}issued_coupons.issued_coupon_code,
                                    {$prefix}promotions.end_date,
                                    {$prefix}promotions.coupon_validity_in_date,
                                    {$prefix}promotions.status,
                                    {$prefix}promotions.promotion_type,
                                    CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                        THEN {$prefix}campaign_status.campaign_status_name
                                        ELSE (
                                            CASE WHEN {$prefix}promotions.end_date < (
                                                SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                FROM {$prefix}promotion_retailer opt
                                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                            )
                                            THEN 'expired'
                                            ELSE {$prefix}campaign_status.campaign_status_name
                                            END
                                        )
                                    END AS campaign_status,
                                    CASE WHEN {$prefix}promotions.coupon_validity_in_date < (
                                            SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                            FROM {$prefix}promotion_retailer opt
                                                LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                            WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                        )
                                        THEN 'true'
                                        ELSE 'false'
                                    END AS is_exceeding_validity_date,
                                    CASE WHEN (
                                        SELECT count(opt.promotion_retailer_id)
                                        FROM {$prefix}promotion_retailer opt
                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                        WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                        AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}promotions.begin_date and {$prefix}promotions.coupon_validity_in_date) > 0
                                    THEN 'true'
                                    ELSE 'false'
                                    END AS is_started,
                                    {$prefix}issued_coupons.issued_coupon_id,
                                    CASE WHEN {$prefix}issued_coupons.status = 'issued' THEN
                                        CASE WHEN {$prefix}promotions.is_payable_by_wallet = 'Y' THEN 'payable' ELSE 'redeemable' END
                                        ELSE 'redeemed' END as issued_coupon_status,
                                    {$prefix}merchants.name as store_name,
                                    CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN malls.name ELSE NULL END as mall_name,
                                    {$prefix}issued_coupons.redeemed_date,

                                    0 as total_redeemed,
                                    0 as total_issued,

                                    CASE WHEN {$prefix}promotions.maximum_redeem = '0' THEN {$prefix}promotions.maximum_issued_coupon ELSE {$prefix}promotions.maximum_redeem END maximum_redeem,
                                    {$prefix}promotions.maximum_issued_coupon,
                                    {$prefix}promotions.available,
                                    {$prefix}promotions.is_unique_redeem,

                                    CASE WHEN {$prefix}promotions.maximum_redeem > 0
                                    THEN
                                        CASE WHEN (SELECT COUNT(oic.issued_coupon_id) FROM {$prefix}issued_coupons oic WHERE oic.status = 'redeemed' AND oic.promotion_id = {$prefix}promotions.promotion_id) >= {$prefix}promotions.maximum_redeem
                                        THEN 0
                                        ELSE ({$prefix}promotions.maximum_redeem - (SELECT COUNT(oic.issued_coupon_id) FROM {$prefix}issued_coupons oic WHERE oic.status = 'redeemed' AND oic.promotion_id = {$prefix}promotions.promotion_id))
                                        END
                                    ELSE (SELECT COUNT(oic.issued_coupon_id) FROM {$prefix}issued_coupons oic WHERE oic.status not in ('redeemed', 'deleted') AND oic.promotion_id = {$prefix}promotions.promotion_id)
                                    END AS available_for_redeem,

                                    (SELECT substring_index(group_concat(distinct om.name SEPARATOR ', '), ', ', 2)
                                        FROM {$prefix}promotion_retailer opr
                                        JOIN {$prefix}merchants om
                                            ON om.merchant_id = opr.retailer_id
                                        WHERE opr.promotion_id = {$prefix}promotions.promotion_id
                                        GROUP BY opr.promotion_id
                                        ORDER BY om.name
                                    ) as link_to_tenant,

                                    (SELECT count(distinct om.name) - 2
                                        FROM {$prefix}promotion_retailer opr
                                        JOIN {$prefix}merchants om
                                            ON om.merchant_id = opr.retailer_id
                                        WHERE opr.promotion_id = {$prefix}promotions.promotion_id
                                    ) as total_link_to_tenant
                                "),
                                'issued_coupons.issued_date'
                            )
                            ->leftJoin('coupon_sepulsa', 'promotions.promotion_id', '=', 'coupon_sepulsa.promotion_id')
                            ->leftJoin('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
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
                            ->join('issued_coupons', function ($join) {
                                $join->on('issued_coupons.promotion_id', '=', 'promotions.promotion_id');
                                $join->on('issued_coupons.status', 'IN', DB::raw("('issued', 'redeemed')"));
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
                            ->where('issued_coupons.user_id', $user->user_id)
                            ->havingRaw("(campaign_status = 'ongoing' OR campaign_status = 'expired')");

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

            // need subquery to order my coupon
            //---------------START SLOW QUERY--------------------
            // TODO:need to fix as this is SLOW DOWN actual query
            $querySql = $coupon->toSql();
            $coupon = DB::table(DB::Raw("({$querySql}) as sub_query"))->mergeBindings($coupon->getQuery())
                            ->select(
                                DB::raw("sub_query.*"),
                                DB::raw("CASE WHEN issued_coupon_status = 'redeemed' THEN 2
                                            ELSE CASE WHEN available_for_redeem = 0 THEN 3
                                                ELSE CASE WHEN is_exceeding_validity_date = 'true' THEN 3
                                                    ELSE 1
                                                    END
                                                END
                                        END as redeem_order")
                            );

            $coupon = $coupon->orderBy(DB::raw("redeem_order"), 'asc');
            $coupon = $coupon->orderBy(DB::raw("redeemed_date"), 'desc');
            $coupon = $coupon->orderBy(DB::raw("issued_date"), 'desc');
            //---------------END SLOW QUERY--------------------

            $_coupon = clone $coupon;

            $take = PaginationNumber::parseTakeFromGet('coupon');
            $coupon->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $coupon->skip($skip);

            $listcoupon = $coupon->get();
            $listcoupon = $this->getTotalIssuedAndRedeemed($listcoupon);
            $count = RecordCounter::create($_coupon)->count();

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
                    $localPath = (! empty($list->localPath)) ? $list->localPath : $localPath;
                    $cdnPath = (! empty($list->cdnPath)) ? $list->cdnPath : $cdnPath;
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
