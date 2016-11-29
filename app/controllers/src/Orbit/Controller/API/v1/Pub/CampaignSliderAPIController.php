<?php namespace Orbit\Controller\API\v1\Pub;
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
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use Mall;
use News;
use Coupon;
use Validator;
use Language;
use stdClass;
use \DB;
use Orbit\Helper\Util\PaginationNumber;
use Orbit\Controller\API\v1\Pub\News\NewsHelper;

class CampaignSliderAPIController extends PubControllerAPI
{
    /**
     * GET - check if mall inside map area
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string area
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCampaignSlider()
    {
        $httpCode = 200;
        try{
            $sort_by = OrbitInput::get('sortby', 'news_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');
            $ul = OrbitInput::get('ul', null);
            $mallId = OrbitInput::get('mall_id', null);
            $maxSlide = OrbitInput::get('take', 10);

            $this->registerCustomValidation();

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

            $lang = Language::where('status', '=', 'active')
                            ->where('name', $language)
                            ->first();
            $language_id = $lang->language_id;
            $prefix = DB::getTablePrefix();

            $withMallId = '';
            if (! empty($mallId)) {
                $withMallId = "AND (CASE WHEN om.object_type = 'tenant' THEN oms.merchant_id ELSE om.merchant_id END) = {$this->quote($mallId)}";
            }

            $news = News::select(
                                'news.news_id as campaign_id',
                                DB::Raw("
                                    CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN {$prefix}news.news_name ELSE {$prefix}news_translations.news_name END as campaign_name,
                                    CASE WHEN {$prefix}media.path is null THEN (
                                            select m.path
                                            from {$prefix}news_translations nt
                                            join {$prefix}media m
                                                on m.object_id = nt.news_translation_id
                                                and m.media_name_long = 'news_translation_image_orig'
                                            where nt.news_id = {$prefix}news.news_id
                                            group by nt.news_id
                                        ) ELSE {$prefix}media.path END as image_url
                                "),
                                'news.object_type as campaign_type',
                        // query for get status active based on timezone
                        DB::raw("
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                        THEN {$prefix}campaign_status.campaign_status_name
                                        ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                    FROM {$prefix}news_merchant onm
                                                                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                    WHERE onm.news_id = {$prefix}news.news_id
                                                                                    {$withMallId})
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status,
                                CASE WHEN (SELECT count(onm.merchant_id)
                                            FROM {$prefix}news_merchant onm
                                                LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                            WHERE onm.news_id = {$prefix}news.news_id
                                            {$withMallId}
                                            AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}news.begin_date and {$prefix}news.end_date) > 0
                                THEN 'true' ELSE 'false' END AS is_started
                            "))
                        ->leftJoin('news_translations', function ($q) use ($language_id) {
                            $q->on('news_translations.news_id', '=', 'news.news_id')
                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($language_id)}"));
                        })
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('media', function($q) {
                            $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                            $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                        })
                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('merchants as m', function($q) {
                                $q->on(DB::raw("m.merchant_id"), '=', 'news_merchant.merchant_id');
                                $q->on(DB::raw("m.status"), '=', DB::raw("'active'"));
                        })
                        ->whereRaw("{$prefix}news.object_type = 'promotion'")
                        ->whereRaw("{$prefix}news.sticky_order = 1")
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->groupBy('campaign_id');

            $coupons = Coupon::select(DB::raw("{$prefix}promotions.promotion_id as campaign_id,
                                CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN {$prefix}promotions.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as campaign_name,
                                CASE WHEN {$prefix}media.path is null THEN (
                                                select m.path
                                                from {$prefix}coupon_translations ct
                                                join {$prefix}media m
                                                    on m.object_id = ct.coupon_translation_id
                                                    and m.media_name_long = 'coupon_translation_image_orig'
                                                where ct.promotion_id = {$prefix}promotions.promotion_id
                                                group by ct.promotion_id
                                            ) ELSE {$prefix}media.path END as image_url,
                                'coupon' as campaign_type,
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name
                                    ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                    FROM {$prefix}promotion_retailer opt
                                                                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                    WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                                                                    {$withMallId})
                                    THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status,
                                CASE WHEN (SELECT count(opt.promotion_retailer_id)
                                            FROM {$prefix}promotion_retailer opt
                                                LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                            WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                            {$withMallId}
                                            AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}promotions.begin_date and {$prefix}promotions.end_date) > 0
                                THEN 'true' ELSE 'false' END AS is_started"))
                            ->leftJoin('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                            ->leftJoin('coupon_translations', function ($q) use ($language_id) {
                                $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                                  ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($language_id)}"));
                            })
                            ->leftJoin('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id')
                            ->leftJoin('media', function($q) {
                                $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                                $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                            })
                            ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('merchants as t', DB::raw("t.merchant_id"), '=', 'promotion_retailer.retailer_id')
                            ->leftJoin('merchants as m', DB::raw("m.merchant_id"), '=', DB::raw("t.parent_id"))
                            ->leftJoin(DB::raw("(SELECT promotion_id, COUNT(*) as tot FROM {$prefix}issued_coupons WHERE status = 'available' GROUP BY promotion_id) as available"), DB::raw("available.promotion_id"), '=', 'promotions.promotion_id')
                            ->whereRaw("{$prefix}promotions.sticky_order = 1")
                            ->whereRaw("available.tot > 0")
                            ->whereRaw("{$prefix}promotion_rules.rule_type != 'blast_via_sms'")
                            ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                            ->groupBy('campaign_id');

            OrbitInput::get('mall_id', function ($mallId) use ($coupons, $news, $prefix) {
                $news = $news->whereRaw("(CASE WHEN m.object_type = 'tenant' THEN m.parent_id ELSE m.merchant_id END) = '{$mallId}'");
                $coupons = $coupons->whereRaw("(CASE WHEN t.object_type = 'tenant' THEN t.parent_id ELSE t.merchant_id END) = '{$mallId}'");
            });

            $newsSql = $news->toSql();
            $newsSql = DB::table(DB::Raw("({$newsSql}) as sub_query"))->mergeBindings($news->getQuery())->toSql();

            $couponSql = $coupons->toSql();
            $couponSql = DB::table(DB::Raw("({$couponSql}) as sub_query"))->mergeBindings($coupons->getQuery())->toSql();

            $campaign = DB::table(DB::raw('((' . $newsSql . ') UNION (' . $couponSql . ')) as a'));

            $_campaign = clone($campaign);

            $totalRec = count($_campaign->get());
            $slideshow = $campaign->get();

            $slide_fix = array();
            $random = array();

            // random process
            if (count($slideshow) > 1) {
                if (count($slideshow) < $maxSlide) {
                    $maxSlide = count($slideshow);
                }

                $slides = array();
                $listSlide = array_rand($slideshow, $maxSlide);
                if (count($listSlide) > 1) {
                    foreach ($listSlide as $key => $value) {
                        array_push($slides, $slideshow[$value]);
                    }

                    $keys = array_keys($slides);
                    shuffle($keys);
                    foreach ($keys as $key) {
                        array_push($random, $slides[$key]);
                    }
                } else {
                    $random = $slideshow[$listSlide];
                }
            } else {
                $random = $slideshow;
            }

            $data = new \stdclass();
            $data->returned_records = count($random);;
            $data->total_records = $totalRec;
            $data->records = $random;

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';

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
            $this->response->data = [$e->getMessage(), $e->getFile(), $e->getLine()];
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    public function registerCustomValidation() {
        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            return TRUE;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}