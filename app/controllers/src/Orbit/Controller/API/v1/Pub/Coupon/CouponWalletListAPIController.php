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
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;
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
        $couponIds = array();
        foreach ($coupons as $key => $coupon) {
            $couponIds[] = $coupon->promotion_id;
        }

        $couponIds = array_unique($couponIds);

        if (! empty($couponIds)) {
            $prefix = DB::getTablePrefix();
            $issuedCoupons = IssuedCoupon::select(DB::raw("
                {$prefix}issued_coupons.promotion_id,
                COUNT({$prefix}issued_coupons.issued_coupon_id) AS total_issued,
                SUM({$prefix}issued_coupons.status = 'redeemed') AS total_redeemed
            "))
            ->whereIn("promotion_id", $couponIds)
            ->whereIn("status", array('issued', 'redeemed'))
            ->groupBy("promotion_id")
            ->get();

            $couponStats = array();
            foreach ($issuedCoupons as $issued) {
                $couponStats[$issued->promotion_id] = array(
                    'total_issued' => $issued->total_issued,
                    'total_redeemed' => $issued->total_redeemed
                );
            }

            foreach ($coupons as $coupon) {
                $coupon->total_issued = $couponStats[$coupon->promotion_id]['total_issued'];
                $coupon->total_redeemed = $couponStats[$coupon->promotion_id]['total_redeemed'];
            }
        }

        return $coupons;
    }

    private function calculateCount($prefix, $user)
    {
        $coupon = Coupon::select(DB::raw("
                                COUNT({$prefix}promotions.promotion_id) as tot_promotion_id
                            ")
                        )
                        ->join('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                        ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                        ->join('issued_coupons', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                        ->where('issued_coupons.user_id', $user->user_id)
                        ->where('is_coupon', 'Y')
                        ->whereIn("campaign_status.campaign_status_name", array('ongoing', 'expired'))
                        ->take(1);
        $totPromo = $coupon->first();
        return (int) $totPromo->tot_promotion_id;
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
                                    {$prefix}issued_coupons.url,
                                    {$prefix}issued_coupons.transfer_status,
                                    {$prefix}promotions.end_date,
                                    {$prefix}issued_coupons.expired_date as coupon_validity_in_date,
                                    {$prefix}promotions.status,
                                    {$prefix}promotions.promotion_type,
                                    {$prefix}promotions.is_3rd_party_promotion,
                                    {$prefix}promotions.redemption_link,
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
                                    CASE WHEN {$prefix}issued_coupons.expired_date < (
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
                                    {$prefix}issued_coupons.issued_coupon_id,
                                    CASE WHEN {$prefix}issued_coupons.status = 'issued' THEN
                                        CASE WHEN {$prefix}promotions.is_payable_by_wallet = 'Y' THEN 'payable' ELSE 'redeemable' END
                                        ELSE 'redeemed' END as issued_coupon_status,
                                    {$prefix}merchants.name as store_name,
                                    CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN malls.name ELSE NULL END as mall_name,
                                    {$prefix}issued_coupons.redeemed_date,

                                    CASE WHEN {$prefix}promotions.maximum_redeem = '0' THEN {$prefix}promotions.maximum_issued_coupon ELSE {$prefix}promotions.maximum_redeem END maximum_redeem,
                                    {$prefix}promotions.maximum_issued_coupon,
                                    {$prefix}promotions.available,
                                    {$prefix}promotions.is_unique_redeem,
                                    {$prefix}promotions.available AS available_for_redeem,

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
                                    ) as total_link_to_tenant,

                                    (SELECT distinct opr.object_type
                                     FROM {$prefix}promotion_retailer opr
                                     WHERE opr.promotion_id = {$prefix}promotions.promotion_id
                                     LIMIT 1
                                    ) as link_to_tenant_type
                                "),
                                'issued_coupons.issued_date'
                            )
                            ->leftJoin('coupon_sepulsa', 'promotions.promotion_id', '=', 'coupon_sepulsa.promotion_id')
                            ->Join('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
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
                            //due to requirement of coupon transfer (OM-5453),
                            //we need to display giftn coupon MyWallet too
                            //->where('promotion_type', '<>', 'gift_n_coupon')
                            ->whereIn("campaign_status.campaign_status_name", array('ongoing', 'expired'));


            OrbitInput::get('type', function($type) use ($coupon) {

                switch ($type) {
                    case 'redeemable':
                        $coupon->havingRaw("issued_coupon_status = 'redeemable'")
                               ->havingRaw("is_exceeding_validity_date = 'false'");
                        break;
                    case 'redeemed':
                        $coupon->havingRaw("issued_coupon_status = 'redeemed'");
                        break;
                    case 'expired':
                        $coupon->havingRaw("is_exceeding_validity_date = 'true'")
                               ->havingRaw("issued_coupon_status != 'redeemed'");
                        break;
                    default:
                }
            });

            //remove code related to Mall because Coupon list in My wallet
            //does not affected by GTM/mall page also remove code related to
            //filter because we do not have filtering in my wallet

            // requirement need us to order coupon that is redeemable and payable
            // to display first, redeemed and expired will come after that
            //->orderByRaw(DB::Raw("FIELD({$prefix}issued_coupons.status, 'issued', 'redeemed', 'expired')"))
                    // This part for ordering coupon with maximum reach condition
                    // ->orderByRaw(DB::Raw("CASE WHEN total_redeemed = maximum_redeem THEN 0 ELSE 1 END DESC"))
            $coupon->orderBy(DB::raw("is_exceeding_validity_date"), 'asc')
                    ->orderBy('issued_coupons.redeemed_date', 'desc')
                    ->orderBy('issued_coupons.issued_date', 'desc');

            $_coupon = clone $coupon;

            $take = PaginationNumber::parseTakeFromGet('coupon');
            $coupon->take($take);
            $skip = PaginationNumber::parseSkipFromGet();
            $coupon->skip($skip);

            $listcoupon = $coupon->get();
            $listcoupon = $this->getTotalIssuedAndRedeemed($listcoupon);

            $count = RecordCounter::create($_coupon)->count();

            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGeneratorWithCloudfront::create(['cdn' => $cdnConfig], 'cdn');
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
