<?php namespace Orbit\Controller\API\v1\Pub\News;

/**
 * @author Irianto <irianto@dominopos.com>
 * @desc Controller for news list you might also like
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \URL;
use News;
use NewsMerchant;
use Language;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Controller\API\v1\Pub\News\NewsHelper;
use Mall;
use Orbit\Helper\Util\GTMSearchRecorder;
use Orbit\Helper\Util\CdnUrlGenerator;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use Orbit\Helper\Util\ObjectPartnerBuilder;

class NewsAlsoLikeListAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    /**
     * GET - get news you might also like
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchNews()
    {
        $httpCode = 200;
        $user = null;
        $mall = null;

        try{
            $user = $this->getUser();
            $show_total_record = OrbitInput::get('show_total_record', null);
            // variable for function
            $except_id = OrbitInput::get('except_id'); // except news id on detail page
            $category_id = OrbitInput::get('category_id'); // filter category id
            $partner_id = OrbitInput::get('partner_id'); // filter partner_id
            $location = OrbitInput::get('location', null);
            $ul = OrbitInput::get('ul', null);
            $lon = '';
            $lat = '';
            $mallId = OrbitInput::get('mall_id', null);

            $param = [
                'except_id'   => $except_id,
                'category_id' => $category_id,
                'partner_id'  => $partner_id,
                'location'    => $location,
                'ul'          => $ul,
                'lon'         => $lon,
                'lat'         => $lat,
                'mallId'      => $mallId,
                'filter'      => 'Y',
            ];

            if (! empty($category_id)) {
                $news_clp = $this->generateQuery($param);

                $param['category_id'] = null;
            }

            $news_dplp = $this->generateQuery($param);

            $param['partner_id'] = null;
            $param['location'] = null;
            $param['filter'] = 'N'; // pass filter category

            $news_all = $this->generateQuery($param);

            if (! empty($category_id)) {
                $news_union = $news_clp->union($news_dplp)->union($news_all);
            } else {
                $news_union = $news_dplp->union($news_all);
            }

            // news union subquery to take and skip
            $newsSql = $news_union->toSql();
            $news = DB::table(DB::raw("({$newsSql}) as news_union"))->mergeBindings($news_union);

            $totalRec = 0;
            // Set defaul 0 when get variable no_total_records = yes
            if ($show_total_record === 'yes') {
                $_news = clone($news);

                $recordCounter = RecordCounter::create($_news);
                OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($recordCounter->getQueryBuilder());

                $totalRec = $recordCounter->count();
            }

            // Cache the result of database calls
            OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($news);

            $take = PaginationNumber::parseTakeFromGet('you_might_also_like');
            $news->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $news->skip($skip);

            $listOfRec = $news->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = $totalRec;
            if (is_object($mall)) {
                $data->mall_name = $mall->name;
            }
            $data->records = $listOfRec;

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
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    protected function generateQuery($param = array()) {
        $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
        $distance = Config::get('orbit.geo_location.distance', 10);
        $sort_by = 'created_date';
        $sort_mode = 'desc';
        $language = OrbitInput::get('language', 'id');

        $except_id   = $param['except_id'];
        $category_id = $param['category_id'];
        $partner_id  = $param['partner_id'];
        $location    = $param['location'];
        $ul          = $param['ul'];
        $lon         = $param['lon'];
        $lat         = $param['lat'];
        $mallId      = $param['mallId'];
        $filter      = $param['filter'];

        $newsHelper = NewsHelper::create();
        $newsHelper->registerCustomValidation();
        $validator = Validator::make(
            array(
                'language'    => $language,
                'except_id'   => $except_id,
                'sortby'      => $sort_by,
            ),
            array(
                'language'  => 'required|orbit.empty.language_default',
                'except_id' => 'required', // need check news id is exists
                'sortby'    => 'in:name,location,created_date',
            )
        );

        // Run the validation
        if ($validator->fails()) {
            $errorMessage = $validator->messages()->first();
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        $valid_language = $newsHelper->getValidLanguage();
        $prefix = DB::getTablePrefix();

        $withMallId = '';
        if (! empty($mallId)) {
            $withMallId = "AND (CASE WHEN om.object_type = 'tenant' THEN oms.merchant_id ELSE om.merchant_id END) = {$this->quote($mallId)}";
        }

        $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
        $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
        $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

        $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
        if ($usingCdn) {
            $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
        }

        $news = News::select(
                            'news.news_id as news_id',
                            DB::Raw("
                                CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN {$prefix}news.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                                CASE WHEN ({$prefix}news_translations.description = '' or {$prefix}news_translations.description is null) THEN {$prefix}news.description ELSE {$prefix}news_translations.description END as description,
                                (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'news_translation_image_orig'
                                    AND m.object_id = {$prefix}news_translations.news_translation_id) AS image_url
                            "),
                            'news.object_type',
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
                        "),
                    'news.begin_date')
                    ->leftJoin('news_translations', function ($q) use ($valid_language) {
                        $q->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        $q->on('news_translations.news_id', '=', 'news.news_id');
                    })
                    ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                    ->whereRaw("{$prefix}news.object_type = 'news'")
                    ->whereRaw("{$prefix}news.status = 'active'")
                    ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                    ->orderBy('news_name', 'asc');

        // left join when need link to mall
        if ($filter === 'Y' || ! empty($mallId) || $sort_by == 'location') {
            $news = $news->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('merchants as m', function ($q) {
                            $q->on(DB::raw("m.status"), '=', DB::raw("'active'"));
                            $q->on(DB::raw("m.merchant_id"), '=', 'news_merchant.merchant_id');
                        });
        }

        //calculate distance if user using my current location as filter and sort by location for listing
        if ($sort_by == 'location' || $location == 'mylocation') {
            if (! empty($ul)) {
                $position = explode("|", $ul);
                $lon = $position[0];
                $lat = $position[1];
            } else {
                // get lon lat from cookie
                $userLocationCookieArray = isset($_COOKIE[$userLocationCookieName]) ? explode('|', $_COOKIE[$userLocationCookieName]) : NULL;
                if (! is_null($userLocationCookieArray) && isset($userLocationCookieArray[0]) && isset($userLocationCookieArray[1])) {
                    $lon = $userLocationCookieArray[0];
                    $lat = $userLocationCookieArray[1];
                }
            }

            if (! empty($lon) && ! empty($lat)) {
                $news = $news->addSelect(DB::raw("6371 * acos( cos( radians({$lat}) ) * cos( radians( x({$prefix}merchant_geofences.position) ) ) * cos( radians( y({$prefix}merchant_geofences.position) ) - radians({$lon}) ) + sin( radians({$lat}) ) * sin( radians( x({$prefix}merchant_geofences.position) ) ) ) AS distance")
                                            )
                                        ->leftJoin('merchant_geofences', function ($q) use($prefix) {
                                                    $q->on('merchant_geofences.merchant_id', '=',
                                                    DB::raw("CASE WHEN m.object_type = 'tenant' THEN m.parent_id ELSE m.merchant_id END"));
                                            });
            }
        }

        // except news_id on detail page
        $news = $news->where('news.news_id', '!=', $except_id);

        if ($filter === 'Y') {
            if (empty($category_id)) {
                // handle if category is empty will find with same of category on detail page
                $news = $news->leftJoin('category_merchant as cm', function($q) {
                                    $q->on(DB::raw('cm.merchant_id'), '=', DB::raw("m.merchant_id"));
                                    $q->on(DB::raw("m.object_type"), '=', DB::raw("'tenant'"));
                                })
                            ->whereRaw("
                                EXISTS (
                                    SELECT 1
                                    FROM {$prefix}news nw
                                    JOIN {$prefix}news_merchant nwm
                                        ON nwm.news_id = nw.news_id
                                    LEFT JOIN {$prefix}merchants te
                                        ON te.merchant_id = nwm.merchant_id
                                        AND te.object_type = 'tenant'
                                    LEFT JOIN {$prefix}category_merchant ctm
                                        ON ctm.merchant_id = te.merchant_id
                                    WHERE nw.news_id = '{$except_id}'
                                        AND ctm.category_id != ''
                                        AND cm.category_id = ctm.category_id
                                    GROUP BY cm.category_id)
                            ");
            } else {
                if (! is_array($category_id)) {
                    $category_id = (array)$category_id;
                }

                $news = $news->leftJoin('category_merchant as cm', function($q) {
                                $q->on(DB::raw('cm.merchant_id'), '=', DB::raw("m.merchant_id"));
                                $q->on(DB::raw("m.object_type"), '=', DB::raw("'tenant'"));
                            })
                            ->whereIn(DB::raw('cm.category_id'), $category_id);
            }
        }

        if (! empty($partner_id)) {
            $news = ObjectPartnerBuilder::getQueryBuilder($news, $partner_id, 'news');
        }

        if (! empty($mallId)) {
            // filter news by mall id
            $news = $news->where(function($q) use ($mallId){
                        $q->where(DB::raw("m.parent_id"), '=', $mallId)
                          ->orWhere(DB::raw("m.merchant_id"), '=', $mallId);
                    })
                    ->where('news.object_type', '=', 'news');
        }

         // frontend need the mall name
         $mall = null;
         if (! empty($mallId)) {
            $mall = Mall::where('merchant_id', '=', $mallId)->first();
         }

         if (! empty($location)) {
            $news = $news->leftJoin('merchants as mp', function($q) {
                            $q->on(DB::raw("mp.merchant_id"), '=', DB::raw("m.parent_id"));
                            $q->on(DB::raw("mp.object_type"), '=', DB::raw("'mall'"));
                            $q->on(DB::raw("m.status"), '=', DB::raw("'active'"));
                        });

            if ($location === 'mylocation' && !empty($lon) && !empty($lat)) {
                $news = $news->havingRaw("distance <= {$distance}");
            } else {
                $news = $news->where(DB::raw("(CASE WHEN m.object_type = 'tenant' THEN mp.city ELSE m.city END)"), $location);
            }
         }

        // first subquery
        $querySql = $news->toSql();
        $news_query = $news->getQuery();
        $news = DB::table(DB::Raw("({$querySql}) as sub_query"))->mergeBindings($news_query);

        if ($sort_by === 'location' && !empty($lon) && !empty($lat)) {
            $news = $news->select(DB::raw("sub_query.news_id"), 'news_name', 'description', DB::raw("sub_query.object_type"), 'image_url', 'campaign_status', 'is_started', DB::raw("min(distance) as distance"), DB::raw("sub_query.begin_date"))
                                   ->orderBy('distance', 'asc');
        } else {
            $news = $news->select(DB::raw("sub_query.news_id"), 'news_name', 'description', DB::raw("sub_query.object_type"), 'image_url', 'campaign_status', 'is_started', DB::raw("sub_query.begin_date"));
        }

        $news = $news->groupBy(DB::Raw("sub_query.news_id"));

        if ($sort_by !== 'location') {
            // Map the sortby request to the real column name
            $sortByMapping = array(
                'name'          => 'news_name',
                'created_date'  => 'begin_date'
            );

            $sort_by = $sortByMapping[$sort_by];
        }

        OrbitInput::get('sortmode', function($_sortMode) use (&$sort_mode)
        {
            if (strtolower($_sortMode) !== 'asc') {
                $sort_mode = 'desc';
            }
        });

        if ($sort_by !== 'location') {
            $news = $news->orderBy($sort_by, $sort_mode);
        }

        $take = Config::get('orbit.pagination.you_might_also_like.max_record');
        $news->take($take);

        // second subquery merging for keep sort by before union
        $_news = clone $news;
        $_querysql = $_news->toSql();
        $_news = DB::table(DB::raw("({$_querysql}) as news_subquery"))->mergeBindings($_news);

        return $_news;
    }
}