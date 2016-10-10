<?php namespace Orbit\Controller\API\v1\Pub\News;

/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for news list and search in landing page
 */

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\ResponseProvider;
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
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Session\UserGetter;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Controller\API\v1\Pub\News\NewsHelper;
use Mall;

class NewsListAPIController extends ControllerAPI
{
    protected $valid_language = NULL;
    /**
     * GET - get active news in all mall, and also provide for searching
     *
     * @author Firmansyayh <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string keyword
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchNews()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()->setActivityType('view');
        $keyword = null;
        $user = null;
        $mall = null;

        try{
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');
            $location = OrbitInput::get('location', null);
            $ul = OrbitInput::get('ul', null);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $lon = '';
            $lat = '';
            $mallId = OrbitInput::get('mall_id', null);

            $newsHelper = NewsHelper::create();
            $newsHelper->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'language' => $language,
                    'sortby'   => $sort_by,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,location',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $newsHelper->getValidLanguage();
            $prefix = DB::getTablePrefix();

            $news = News::select(
                                'news.news_id as news_id',
                                DB::Raw("
                                    CASE WHEN {$prefix}news_translations.news_name = '' THEN {$prefix}news.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                                    CASE WHEN {$prefix}news_translations.description = '' THEN {$prefix}news.description ELSE {$prefix}news_translations.description END as description,
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
                                                                                    WHERE onm.news_id = {$prefix}news.news_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status,
                                CASE WHEN (SELECT count(onm.merchant_id)
                                            FROM {$prefix}news_merchant onm
                                                LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                            WHERE onm.news_id = {$prefix}news.news_id
                                            AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}news.begin_date and {$prefix}news.end_date) > 0
                                THEN 'true' ELSE 'false' END AS is_started
                            "),
                        'news.created_at')
                        ->join('news_translations', 'news_translations.news_id', '=', 'news.news_id')
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
                        ->where('news_translations.merchant_language_id', '=', $valid_language->language_id)
                        ->where('news.object_type', '=', 'news')
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->orderBy('news_name', 'asc');



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

            // filter by category_id
            OrbitInput::get('category_id', function($category_id) use ($news, $prefix) {
                if ($category_id === 'mall') {
                    $news = $news->where(DB::raw("m.object_type"), $category_id);
                } else {
                    $news = $news->leftJoin('category_merchant as cm', function($q) {
                                    $q->on(DB::raw('cm.merchant_id'), '=', DB::raw("m.merchant_id"));
                                    $q->on(DB::raw("m.object_type"), '=', DB::raw("'tenant'"));
                                })
                        ->where(DB::raw('cm.category_id'), $category_id);
                }
            });

            // filter news by mall id
             OrbitInput::get('mall_id', function($mallid) use ($news) {
                $news->where(function($q) use ($mallid){
                        $q->where(DB::raw("m.parent_id"), '=', $mallid)
                          ->orWhere(DB::raw("m.merchant_id"), '=', $mallid);
                    });
             });

             // frontend need the mall name
             $mall = null;
             if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
             }

            // filter by city
            OrbitInput::get('location', function($location) use ($news, $prefix, $lon, $lat, $userLocationCookieName, $distance) {
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
            });

            $querySql = $news->toSql();

            $news = DB::table(DB::Raw("({$querySql}) as sub_query"))->mergeBindings($news->getQuery());

            if ($sort_by === 'location' && !empty($lon) && !empty($lat)) {
                $news = $news->select(DB::raw("sub_query.news_id"), 'news_name', 'description', DB::raw("sub_query.object_type"), 'image_url', 'campaign_status', 'is_started', DB::raw("min(distance) as distance"))
                                       ->orderBy('distance', 'asc');
            } else {
                $news = $news->select(DB::raw("sub_query.news_id"), 'news_name', 'description', DB::raw("sub_query.object_type"), 'image_url', 'campaign_status', 'is_started');
            }

            $news = $news->groupBy(DB::Raw("sub_query.news_id"));

            OrbitInput::get('sortby', function($_sortBy) use (&$sort_by)
            {
                if ($sort_by !== 'location') {
                    // Map the sortby request to the real column name
                    $sortByMapping = array(
                        'name'          => 'news_name',
                        'created_date'  => 'created_at'
                    );

                    $sort_by = $sortByMapping[$_sortBy];
                }
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sort_mode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sort_mode = 'desc';
                }
            });

            if ($sort_by !== 'location') {
                $news = $news->orderBy($sort_by, $sort_mode);
            }

            OrbitInput::get('keyword', function($keyword) use ($news, $prefix) {
                 if (! empty($keyword)) {
                    $news = $news->leftJoin('keyword_object', DB::Raw("sub_query.news_id"), '=', 'keyword_object.object_id')
                                ->leftJoin('keywords', 'keyword_object.keyword_id', '=', 'keywords.keyword_id')
                                ->where(function($query) use ($keyword, $prefix){
                                    //Search per word
                                    $words = explode(' ', $keyword);
                                    foreach ($words as $key => $word) {
                                        if (strlen($word) === 1 && $word === '%') {
                                            $query->orWhere(function($q) use ($word, $prefix){
                                                $q->whereRaw("sub_query.news_name like '%|{$word}%' escape '|'")
                                                  ->orWhereRaw("sub_query.description like '%|{$word}%' escape '|'")
                                                  ->orWhereRaw("{$prefix}keywords.keyword like '%|{$word}%' escape '|'");
                                            });
                                        } else {
                                            $query->orWhere(function($q) use ($word, $prefix){
                                                $q->where(DB::raw('sub_query.news_name'), 'like', '%' . $word . '%')
                                                  ->orWhere(DB::raw('sub_query.description'), 'like', '%' . $word . '%')
                                                  ->orWhere('keywords.keyword', 'like', '%' . $word . '%');
                                            });
                                        }
                                    }
                                });
                 }
            });

            OrbitInput::get('filter_name', function ($filterName) use ($news, $prefix) {
                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $news->whereRaw("SUBSTR(sub_query.news_name,1,1) not between 'a' and 'z'");
                    } else {
                        $filter = explode("-", $filterName);
                        $news->whereRaw("SUBSTR(sub_query.news_name,1,1) between {$this->quote($filter[0])} and {$this->quote($filter[1])}");
                    }
                }
            });

            $_news = clone($news);

            $take = PaginationNumber::parseTakeFromGet('news');
            $news->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $news->skip($skip);

            $totalRec = count($_news->get());
            $listOfRec = $news->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = $totalRec;
            if (is_object($mall)) {
                $data->mall_name = $mall->name;
            }
            $data->records = $listOfRec;

            if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                $activityNotes = sprintf('Page viewed: News list');
                $activity->setUser($user)
                    ->setActivityName('view_news_main_page')
                    ->setActivityNameLong('View News Main Page')
                    ->setObject(null)
                    ->setLocation($mall)
                    ->setModuleName('News')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}