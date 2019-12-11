<?php namespace Orbit\Controller\API\v1\Pub\Store;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;
use Config;
use Mall;
use News;
use Tenant;
use Advert;
use stdClass;
use DB;
use Validator;
use Language;
use Coupon;
use Activity;
use Lang;


class StoreCampaignListAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $store = NULL;
    protected $withoutScore = FALSE;

    /**
     * GET - get campaign store list after click store name
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     * @param string store_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCampaignStoreList()
    {
        $httpCode = 200;
        try {
            $sort_by = OrbitInput::get('sortby', 'campaign_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $merchant_id = OrbitInput::get('merchant_id');
            $store_name = OrbitInput::get('store_name', '');
            $keyword = OrbitInput::get('keyword');
            $language = OrbitInput::get('language', 'id');
            $location = OrbitInput::get('location', null);
            $countryFilter = OrbitInput::get('country', null);
            $citiesFilter = OrbitInput::get('cities', null);
            $category_id = OrbitInput::get('category_id');
            $token = OrbitInput::get('token');
            $ul = OrbitInput::get('ul', null);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $lon = '';
            $lat = '';

            // Call validation from store helper
            $this->registerCustomValidation();
            // $storeHelper = StoreHelper::create();
            // $storeHelper->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    'language' => $language,
                    'sortby'   => $sort_by,
                ),
                array(
                    'merchant_id' => 'required|orbit.empty.tenant',
                    'language'    => 'required|orbit.empty.language_default',
                    'sortby'      => 'in:campaign_name,name,location,created_date',
                ),
                array(
                    'required'           => 'Merchant id is required',
                    'orbit.empty.tenant' => Lang::get('validation.orbit.empty.tenant'),
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();

            $store = Tenant::select('merchants.merchant_id', 'merchants.name', DB::raw('oms.country_id'))
                        ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                        ->where('merchants.merchant_id', $merchant_id)
                        ->where('merchants.status', '=', 'active')
                        ->where(DB::raw('oms.status'), '=', 'active')
                        ->first();

            $country_id = '';
            if (! empty($store)) {
                $store_name = $store->name;
                $country_id = $store->country_id;
            }

            // get news list
            $news = DB::table('news')->select(
                        'news.news_id as campaign_id',
                        'news.begin_date as begin_date',
                        DB::Raw(" CASE WHEN {$prefix}partners.partner_id is null THEN {$prefix}news.is_exclusive ELSE 'N' END as is_exclusive "),
                        DB::Raw(" CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as campaign_name "),
                        'news.object_type as campaign_type',
                        'news.is_having_reward',
                        // query for get status active based on timezone
                        DB::raw("
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                THEN {$prefix}campaign_status.campaign_status_name
                                ELSE (
                                    CASE WHEN {$prefix}news.end_date < (
                                        SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                        FROM {$prefix}news_merchant onm
                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                        WHERE onm.news_id = {$prefix}news.news_id
                                    )
                                    THEN 'expired'
                                    ELSE {$prefix}campaign_status.campaign_status_name
                                    END
                                )
                                END AS campaign_status,
                                CASE WHEN (
                                    SELECT count(onm.merchant_id)
                                    FROM {$prefix}news_merchant onm
                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                    WHERE onm.news_id = {$prefix}news.news_id
                                    AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}news.begin_date and {$prefix}news.end_date) > 0
                                THEN 'true'
                                ELSE 'false'
                                END AS is_started,
                                CASE WHEN {$prefix}media.path is null THEN med.path ELSE {$prefix}media.path END as localPath,
                                CASE WHEN {$prefix}media.cdn_url is null THEN med.cdn_url ELSE {$prefix}media.cdn_url END as cdnPath
                            "))
                        ->leftJoin('news_translations', function ($q) use ($valid_language) {
                            $q->on('news_translations.news_id', '=', 'news.news_id')
                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })
                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('media', function ($q) {
                            $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                            $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                        })
                        ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                        ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                        ->leftJoin('news_translations as default_translation', function ($q) use ($prefix){
                            $q->on(DB::raw("default_translation.news_id"), '=', 'news.news_id')
                              ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                        })
                        ->leftJoin('media as med', function ($q) {
                            $q->on(DB::raw('med.object_id'), '=', DB::raw('default_translation.news_translation_id'));
                            $q->on(DB::raw('med.media_name_long'), '=', DB::raw("'news_translation_image_orig'"));
                        })
                        // Exclusive partner
                        ->leftJoin('object_partner', function ($q) {
                            $q->on('object_partner.object_id', '=', 'news.news_id');
                            $q->on('object_partner.object_type', '=',  DB::raw("'news'"));
                        })
                        ->leftJoin('partners', function ($q) use($token) {
                            $q->on('partners.partner_id', '=', 'object_partner.partner_id');
                            $q->on('partners.token', '=', DB::raw("{$this->quote($token)}"));
                        })
                        ->whereRaw("{$prefix}merchants.merchant_id in (select merchant_id from {$prefix}merchants where name = {$this->quote($this->store->name)})")
                        ->where(function($q) use($country_id, $prefix) {
                            $q->whereRaw("{$prefix}merchants.country_id = {$this->quote($country_id)}")
                                ->orWhereRaw("oms.country_id = {$this->quote($country_id)}");
                        })
                        ->whereRaw("{$prefix}news.object_type = 'news'")
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->groupBy('campaign_id')
                        ->orderBy('news.created_at', 'desc');

            // filter by mall id
            OrbitInput::get('mall_id', function($mallid) use ($news) {
                $news->where(function($q) use($mallid) {
                            $q->where('merchants.parent_id', '=', $mallid)
                                ->orWhere('merchants.merchant_id', '=', $mallid);
                        });
            });

            if (! empty($countryFilter) || ! empty($citiesFilter)) {
                $news->join('merchants as mp', function($q) use ($prefix) {
                                $q->on(DB::raw("mp.merchant_id"), '=', DB::raw("{$prefix}merchants.parent_id"));
                                $q->on(DB::raw("mp.object_type"), '=', DB::raw("'mall'"));
                                $q->on(DB::raw("{$prefix}merchants.status"), '=', DB::raw("'active'"));
                            });
            }

            // filter by country
            OrbitInput::get('country', function($country) use ($news, $prefix) {
                $news = $news->where(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN mp.country ELSE {$prefix}merchants.country END)"), $country);
            });

            // filter by country
            OrbitInput::get('cities', function($cities) use ($news, $prefix) {
                if (! is_array($cities)) {
                    $cities = (array) $cities;
                }

                $news = $news->whereIn(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN mp.city ELSE {$prefix}merchants.city END)"), $cities);
            });

            // filter by category_id
            OrbitInput::get('category_id', function($category_id) use ($news, $prefix) {
                if (! is_array($category_id)) {
                    $category_id = (array)$category_id;
                }

                if (in_array("mall", $category_id)) {
                    $news = $news->whereIn('merchants', $category_id);
                } else {
                    $news = $news->leftJoin('category_merchant', function($q) {
                                    $q->on('category_merchant.merchant_id', '=', 'merchants.merchant_id');
                                    $q->on('merchants.object_type', '=', DB::raw("'tenant'"));
                                })
                        ->whereIn('category_merchant.category_id', $category_id);
                }
            });

            OrbitInput::get('partner_id', function($partner_id) use ($news) {
                $news = ObjectPartnerBuilder::getQueryBuilder($news, $partner_id, 'news');
            });

            $promotions = DB::table('news')->select(
                        'news.news_id as campaign_id',
                        'news.begin_date as begin_date',
                        DB::Raw(" CASE WHEN {$prefix}partners.partner_id is null THEN {$prefix}news.is_exclusive ELSE 'N' END as is_exclusive "),
                        DB::Raw(" CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as campaign_name "),
                        'news.object_type as campaign_type',
                        'is_having_reward',
                        // query for get status active based on timezone
                        DB::raw("
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                THEN {$prefix}campaign_status.campaign_status_name
                                ELSE (
                                    CASE WHEN {$prefix}news.end_date < (
                                        SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                        FROM {$prefix}news_merchant onm
                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                        WHERE onm.news_id = {$prefix}news.news_id
                                    )
                                    THEN 'expired'
                                    ELSE {$prefix}campaign_status.campaign_status_name
                                    END
                                )
                                END AS campaign_status,
                                CASE WHEN (
                                    SELECT count(onm.merchant_id)
                                    FROM {$prefix}news_merchant onm
                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                    WHERE onm.news_id = {$prefix}news.news_id
                                    AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}news.begin_date and {$prefix}news.end_date) > 0
                                THEN 'true'
                                ELSE 'false'
                                END AS is_started,
                                CASE WHEN {$prefix}media.path is null THEN med.path ELSE {$prefix}media.path END as localPath,
                                CASE WHEN {$prefix}media.cdn_url is null THEN med.cdn_url ELSE {$prefix}media.cdn_url END as cdnPath
                            "))
                        ->leftJoin('news_translations', function ($q) use ($valid_language) {
                            $q->on('news_translations.news_id', '=', 'news.news_id')
                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })
                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('media', function ($q) {
                            $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                            $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                        })
                        ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                        ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                        ->leftJoin('news_translations as default_translation', function ($q) use ($prefix){
                            $q->on(DB::raw("default_translation.news_id"), '=', 'news.news_id')
                              ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                        })
                        ->leftJoin('media as med', function ($q) {
                            $q->on(DB::raw('med.object_id'), '=', DB::raw('default_translation.news_translation_id'));
                            $q->on(DB::raw('med.media_name_long'), '=', DB::raw("'news_translation_image_orig'"));
                        })
                        // Exclusive partner
                        ->leftJoin('object_partner', function ($q) {
                            $q->on('object_partner.object_id', '=', 'news.news_id');
                            $q->on('object_partner.object_type', '=',  DB::raw("'promotion'"));
                        })
                        ->leftJoin('partners', function ($q) use($token) {
                            $q->on('partners.partner_id', '=', 'object_partner.partner_id');
                            $q->on('partners.token', '=', DB::raw("{$this->quote($token)}"));
                        })
                        ->whereRaw("{$prefix}merchants.merchant_id in (select merchant_id from {$prefix}merchants where name = {$this->quote($this->store->name)})")
                        ->where(function($q) use($country_id, $prefix) {
                            $q->whereRaw("{$prefix}merchants.country_id = {$this->quote($country_id)}")
                                ->orWhereRaw("oms.country_id = {$this->quote($country_id)}");
                        })
                        ->whereRaw("{$prefix}news.object_type = 'promotion'")
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->groupBy('campaign_id')
                        ->orderBy('news.created_at', 'desc');

            // filter by mall id
            OrbitInput::get('mall_id', function($mallid) use ($promotions) {
                $promotions->where(function($q) use($mallid) {
                            $q->where('merchants.parent_id', '=', $mallid)
                                ->orWhere('merchants.merchant_id', '=', $mallid);
                        });
            });

            if (! empty($countryFilter) || ! empty($citiesFilter)) {
                $promotions->join('merchants as mp', function($q) use ($prefix) {
                                $q->on(DB::raw("mp.merchant_id"), '=', DB::raw("{$prefix}merchants.parent_id"));
                                $q->on(DB::raw("mp.object_type"), '=', DB::raw("'mall'"));
                                $q->on(DB::raw("{$prefix}merchants.status"), '=', DB::raw("'active'"));
                            });
            }

            // filter by country
            OrbitInput::get('country', function($country) use ($promotions, $prefix) {
                $promotions = $promotions->where(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN mp.country ELSE {$prefix}merchants.country END)"), $country);
            });

            // filter by country
            OrbitInput::get('cities', function($cities) use ($promotions, $prefix) {
                if (! is_array($cities)) {
                    $cities = (array) $cities;
                }

                $promotions = $promotions->whereIn(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN mp.city ELSE {$prefix}merchants.city END)"), $cities);
            });

            // filter by category_id
            OrbitInput::get('category_id', function($category_id) use ($promotions, $prefix) {
                if (! is_array($category_id)) {
                    $category_id = (array)$category_id;
                }

                if (in_array("mall", $category_id)) {
                    $promotions = $promotions->whereIn('merchants', $category_id);
                } else {
                    $promotions = $promotions->leftJoin('category_merchant', function($q) {
                                    $q->on('category_merchant.merchant_id', '=', 'merchants.merchant_id');
                                    $q->on('merchants.object_type', '=', DB::raw("'tenant'"));
                                })
                        ->whereIn('category_merchant.category_id', $category_id);
                }
            });

            OrbitInput::get('partner_id', function($partner_id) use ($promotions) {
                $promotions = ObjectPartnerBuilder::getQueryBuilder($promotions, $partner_id, 'promotion');
            });

            // get coupon list
            $coupons = DB::table('promotions')->select(DB::raw("
                                {$prefix}promotions.promotion_id as campaign_id,
                                {$prefix}promotions.begin_date as begin_date,
                                CASE WHEN {$prefix}partners.partner_id is null THEN {$prefix}promotions.is_exclusive ELSE 'N' END as is_exclusive,
                                CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as campaign_name,
                                'coupon' as campaign_type,
                                'N' as is_having_reward,
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                THEN {$prefix}campaign_status.campaign_status_name
                                ELSE (
                                    CASE WHEN {$prefix}promotions.end_date < (
                                        SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                        FROM {$prefix}promotion_retailer opt
                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                        WHERE opt.promotion_id = {$prefix}promotions.promotion_id)
                                    THEN 'expired'
                                    ELSE {$prefix}campaign_status.campaign_status_name
                                    END
                                )
                                END AS campaign_status,
                                CASE WHEN (
                                    SELECT count(opt.promotion_retailer_id)
                                    FROM {$prefix}promotion_retailer opt
                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                    WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                        AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}promotions.begin_date and {$prefix}promotions.end_date) > 0
                                THEN 'true'
                                ELSE 'false'
                                END AS is_started,
                                CASE WHEN {$prefix}media.path is null THEN med.path ELSE {$prefix}media.path END as localPath,
                                CASE WHEN {$prefix}media.cdn_url is null THEN med.cdn_url ELSE {$prefix}media.cdn_url END as cdnPath
                            "))
                            ->leftJoin('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                            ->leftJoin('coupon_translations', function ($q) use ($valid_language) {
                                $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                                  ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                            })
                            ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                            ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                            ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                            ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                            ->leftJoin('coupon_translations as default_translation', function ($q) {
                                $q->on(DB::raw('default_translation.promotion_id'), '=', 'promotions.promotion_id')
                                  ->on(DB::raw('default_translation.merchant_language_id'), '=', 'languages.language_id');
                            })
                            ->leftJoin('media as med', function ($q) {
                                $q->on(DB::raw('med.object_id'), '=', DB::raw('default_translation.coupon_translation_id'));
                                $q->on(DB::raw('med.media_name_long'), '=', DB::raw("'coupon_translation_image_orig'"));
                            })
                            ->leftJoin('media', function($q) {
                                $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                                $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                            })
                            // Exclusive partner
                            ->leftJoin('object_partner', function ($q) {
                                $q->on('object_partner.object_id', '=', 'promotions.promotion_id');
                                $q->on('object_partner.object_type', '=', DB::raw("'coupon'"));
                            })
                            ->leftJoin('partners', function ($q) use($token) {
                                $q->on('partners.partner_id', '=', 'object_partner.partner_id');
                                $q->on('partners.token', '=', DB::raw("{$this->quote($token)}"));
                            })
                            // Available coupon
                            ->leftJoin(DB::raw("(SELECT promotion_id, COUNT(*) as tot FROM {$prefix}issued_coupons WHERE status = 'available' GROUP BY promotion_id) as available"), DB::raw("available.promotion_id"), '=', 'promotions.promotion_id')
                            ->whereRaw("available.tot > 0")
                            ->whereRaw("{$prefix}promotion_rules.rule_type != 'blast_via_sms'")
                            ->whereRaw("{$prefix}merchants.merchant_id in (select merchant_id from {$prefix}merchants where name = {$this->quote($this->store->name)})")
                            ->where(function($q) use($country_id, $prefix) {
                                $q->whereRaw("{$prefix}merchants.country_id = {$this->quote($country_id)}")
                                    ->orWhereRaw("oms.country_id = {$this->quote($country_id)}");
                            })
                            ->whereRaw("{$prefix}promotions.is_visible = 'Y'")
                            ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                            ->groupBy('campaign_id')
                            ->orderBy(DB::raw("{$prefix}promotions.created_at"), 'desc');

            // filter by mall id
            OrbitInput::get('mall_id', function($mallid) use ($coupons) {
                $coupons->where(function($q) use($mallid) {
                            $q->where('merchants.parent_id', '=', $mallid)
                                ->orWhere('merchants.merchant_id', '=', $mallid);
                        });
            });

            if (! empty($countryFilter) || ! empty($citiesFilter)) {
                $coupons->join('merchants as mp', function($q) use ($prefix) {
                                $q->on(DB::raw("mp.merchant_id"), '=', DB::raw("{$prefix}merchants.parent_id"));
                                $q->on(DB::raw("mp.object_type"), '=', DB::raw("'mall'"));
                                $q->on(DB::raw("{$prefix}merchants.status"), '=', DB::raw("'active'"));
                            });
            }

            // filter by country
            OrbitInput::get('country', function($country) use ($coupons, $prefix) {
                $coupons = $coupons->where(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN mp.country ELSE {$prefix}merchants.country END)"), $country);
            });

            // filter by country
            OrbitInput::get('cities', function($cities) use ($coupons, $prefix) {
                if (! is_array($cities)) {
                    $cities = (array) $cities;
                }

                $coupons = $coupons->whereIn(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN mp.city ELSE {$prefix}merchants.city END)"), $cities);
            });

            // filter by category_id
            OrbitInput::get('category_id', function($category_id) use ($coupons, $prefix) {
                if (! is_array($category_id)) {
                    $category_id = (array)$category_id;
                }

                if (in_array("mall", $category_id)) {
                    $coupons = $coupons->whereIn('merchants', $category_id);
                } else {
                    $coupons = $coupons->leftJoin('category_merchant', function($q) {
                                    $q->on('category_merchant.merchant_id', '=', 'merchants.merchant_id');
                                    $q->on('merchants.object_type', '=', DB::raw("'tenant'"));
                                })
                        ->whereIn('category_merchant.category_id', $category_id);
                }
            });

            OrbitInput::get('partner_id', function($partner_id) use ($coupons) {
                $coupons = ObjectPartnerBuilder::getQueryBuilder($coupons, $partner_id, 'coupon');
            });

            $result = $news->unionAll($promotions)->unionAll($coupons);

            $querySql = $result->toSql();

            $campaign = DB::table(DB::Raw("({$querySql}) as campaign"))->mergeBindings($result);

            $_campaign = clone $campaign;

            if ($sort_by !== 'location') {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'campaign_name'   => 'campaign_name',
                    'name'            => 'campaign_name',
                    'created_date'    => 'begin_date',
                );

                $sort_by = $sortByMapping[$sort_by];
            }

            $take = PaginationNumber::parseTakeFromGet('campaign');

            $campaign->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $campaign->skip($skip);

            if ($sort_by !== 'location') {
                $campaign->orderBy($sort_by, $sort_mode);
            }

            $recordCounter = RecordCounter::create($_campaign);
            $totalRec = $recordCounter->count();

            $listcampaign = $campaign->get();
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGeneratorWithCloudfront::create(['cdn' => $cdnConfig], 'cdn');
            $localPath = '';
            $cdnPath = '';
            $listId = '';

            if (count($listcampaign) > 0) {
                foreach ($listcampaign as $list) {
                    if ($listId != $list->campaign_id) {
                        $localPath = '';
                        $cdnPath = '';
                        $list->image_url = '';
                    }
                    $localPath = (! empty($list->localPath)) ? $list->localPath : $localPath;
                    $cdnPath = (! empty($list->cdnPath)) ? $list->cdnPath : $cdnPath;
                    $list->original_media_path = $imgUrl->getImageUrl($localPath, $cdnPath);
                    $listId = $list->campaign_id;
                }
            }

            $this->response->data = new stdClass();
            $this->response->data->total_records = $totalRec;
            $this->response->data->returned_records = count($listcampaign);
            $this->response->data->records = $listcampaign;
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

    protected function registerCustomValidation() {
        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->valid_language = $language;
            return TRUE;
        });

        // Check store is exists
        Validator::extend('orbit.empty.tenant', function ($attribute, $value, $parameters) {
            $store = Tenant::where('status', 'active')
                            ->where('merchant_id', $value)
                            ->first();

            if (empty($store)) {
                return FALSE;
            }

            $this->store = $store;
            return TRUE;
        });
    }


    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
