<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\ControllerAPI;
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
use Tenant;
use Advert;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Language;
use Coupon;
use Activity;
use Orbit\Helper\Util\GTMSearchRecorder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use \Carbon\Carbon as Carbon;

class StoreAPIController extends ControllerAPI
{
    protected $valid_language = NULL;
    /**
     * GET - get all store in all mall, group by name
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
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
    public function getStoreList()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $mall = NULL;
        $user = NULL;
        $httpCode = 200;
        try {
            $this->checkAuth();
            $user = $this->api->user;

            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $location = OrbitInput::get('location', null);
            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $language = OrbitInput::get('language', 'id');
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance');
            $ul = OrbitInput::get('ul');
            $lon = 0;
            $lat = 0;
            $list_type = OrbitInput::get('list_type', 'featured');
            $from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $mallId = OrbitInput::get('mall_id', null);

            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $this->registerCustomValidation();
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

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();

            $advert_location_type = 'gtm';
            $advert_location_id = '0';
            if (! empty($mallId)) {
                $advert_location_type = 'mall';
                $advert_location_id = $mallId;
            }

            $timezone = 'Asia/Jakarta'; // now with jakarta timezone

            $adverts = Advert::select('adverts.advert_id',
                                    'adverts.link_object_id',
                                    'merchants.name',
                                    'advert_placements.placement_type',
                                    'advert_placements.placement_order',
                                    'advert_locations.location_type',
                                    'advert_link_types.advert_link_name')
                            ->join('advert_link_types', function ($q) {
                                $q->on('advert_link_types.advert_link_name', '=', DB::raw("'Store'"));
                                $q->on('advert_link_types.advert_link_type_id', '=', 'adverts.advert_link_type_id');
                            })
                            ->join('advert_locations', function ($q) use ($advert_location_id, $advert_location_type) {
                                $q->on('advert_locations.location_type', '=', DB::raw("'" . $advert_location_type . "'"));
                                $q->on('advert_locations.location_id', '=', DB::raw("'" . $advert_location_id . "'"));
                                $q->on('advert_locations.advert_id', '=', 'adverts.advert_id');
                            })
                            ->join('advert_placements', function ($q) use ($list_type) {
                                $q->on('advert_placements.advert_placement_id', '=', 'adverts.advert_placement_id');
                                if ($list_type === 'featured') {
                                    $q->on('advert_placements.placement_type', 'in', DB::raw("('featured_list', 'preferred_list_regular', 'preferred_list_large')"));
                                } else {
                                    $q->on('advert_placements.placement_type', 'in', DB::raw("('preferred_list_regular', 'preferred_list_large')"));
                                }
                            })
                            ->join('merchants', 'merchants.merchant_id', '=', 'adverts.link_object_id')
                            ->where('adverts.status', '=', DB::raw("'active'"))
                            ->where('adverts.start_date', '<=', DB::raw("CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$timezone}')"))
                            ->where('adverts.end_date', '>=', DB::raw("CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$timezone}')"));

            $advertSql = $adverts->toSql();
            foreach($adverts->getBindings() as $binding)
            {
              $value = is_numeric($binding) ? $binding : $this->quote($binding);
              $advertSql = preg_replace('/\?/', $value, $advertSql, 1);
            }

            $store = Tenant::select(
                    DB::raw("{$prefix}merchants.merchant_id"),
                    DB::raw("{$prefix}merchants.name"),
                    DB::Raw("CASE WHEN (
                                    select mt.description
                                    from {$prefix}merchant_translations mt
                                    where mt.merchant_id = {$prefix}merchants.merchant_id
                                        and mt.merchant_language_id = {$this->quote($valid_language->language_id)}
                                ) = ''
                                THEN {$prefix}merchants.description
                                ELSE (
                                    select mt.description
                                    from {$prefix}merchant_translations mt
                                    where mt.merchant_id = {$prefix}merchants.merchant_id
                                        and mt.merchant_language_id = {$this->quote($valid_language->language_id)}
                                )
                            END as description
                        "),
                    DB::raw("oms.merchant_id as mall_id"),
                    DB::raw("oms.name as mall_name"),
                    DB::raw("CASE WHEN advert_media.path is null THEN {$prefix}media.path
                            ELSE advert_media.path END
                            as logo_url"),
                    DB::raw("advert.placement_type, advert.placement_order"))
                ->join(DB::raw("(select merchant_id, name, status, parent_id, city from {$prefix}merchants where object_type = 'mall') as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                ->leftJoin('media', function($q) {
                    $q->on('media.media_name_long', '=', DB::raw("'retailer_logo_orig'"));
                    $q->on('media.object_id', '=', 'merchants.merchant_id');
                })
                ->leftJoin(DB::raw("({$advertSql}) as advert"), DB::raw("advert.name"), '=', 'merchants.name')
                ->leftJoin('media as advert_media', function ($q) {
                    $q->on(DB::raw("advert_media.media_name_long"), '=', DB::raw("'advert_image_orig'"));
                    $q->on(DB::raw("advert_media.object_id"), '=', DB::raw("advert.advert_id"));
                })
                ->where('merchants.status', 'active')
                ->whereRaw("oms.status = 'active'")
                ->orderBy(DB::raw("advert.placement_order"), 'desc')
                ->orderBy('merchants.created_at', 'asc');

            OrbitInput::get('mall_id', function ($mallId) use ($store, $prefix, &$mall) {
                $store->where('merchants.parent_id', '=', DB::raw("{$this->quote($mallId)}"));
                $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $mallId)
                        ->first();
            });

            // filter by category just on first store
            OrbitInput::get('category_id', function ($category_id) use ($store, $prefix, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
                $store->leftJoin(DB::raw("{$prefix}category_merchant cm"), DB::Raw("cm.merchant_id"), '=', 'merchants.merchant_id')
                    ->where(DB::raw("cm.category_id"), $category_id);
            });

            OrbitInput::get('keyword', function ($keyword) use ($store, $prefix, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
                if (! empty($keyword)) {
                    $store = $store->leftJoin('keyword_object', 'merchants.merchant_id', '=', 'keyword_object.object_id')
                                ->leftJoin('keywords', 'keyword_object.keyword_id', '=', 'keywords.keyword_id')
                                ->where(function($query) use ($keyword, $prefix)
                                {
                                    $word = explode(" ", $keyword);
                                    foreach ($word as $key => $value) {
                                        if (strlen($value) === 1 && $value === '%') {
                                            $query->orWhere(function($q) use ($value, $prefix){
                                                $q->whereRaw("{$prefix}merchants.name like '%|{$value}%' escape '|'")
                                                  ->orWhereRaw("{$prefix}merchants.description like '%|{$value}%' escape '|'")
                                                  ->orWhereRaw("{$prefix}keywords.keyword like '%|{$value}%' escape '|'");
                                            });
                                        } else {
                                            $query->orWhere(function($q) use ($value, $prefix){
                                                $q->where(DB::raw("{$prefix}merchants.name"), 'like', '%' . $value . '%')
                                                  ->orWhere(DB::raw("{$prefix}merchants.description"), 'like', '%' . $value . '%')
                                                  ->orWhere('keywords.keyword', 'like', '%' . $value . '%');
                                            });
                                        }
                                    }
                                });
                }
            });

            if ($sort_by === 'location' || $location === 'mylocation') {
                // prepare my location
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
                    $store = $store->addSelect(
                                        DB::raw("( 6371 * acos( cos( radians({$lat}) ) * cos( radians( x(tmp_mg.position) ) ) * cos( radians( y(tmp_mg.position) ) - radians({$lon}) ) + sin( radians({$lat}) ) * sin( radians( x(tmp_mg.position) ) ) ) ) AS distance")
                                    )
                                    ->leftJoin(DB::Raw("
                                        (SELECT
                                            store.name as store_name,
                                            mg.position
                                        FROM {$prefix}merchants store
                                        LEFT JOIN {$prefix}merchants mall
                                            ON mall.merchant_id = store.parent_id
                                        LEFT JOIN {$prefix}merchant_geofences mg
                                            ON mg.merchant_id = mall.merchant_id
                                        WHERE store.status = 'active'
                                            AND store.object_type = 'tenant'
                                            AND mall.status = 'active'
                                        ) as tmp_mg
                                    "), DB::Raw("tmp_mg.store_name"), '=', 'merchants.name');
                }
            }

            // filter by city before grouping
            $myLocationFilter = FALSE;
            OrbitInput::get('location', function ($location) use ($store, $prefix, $lon, $lat, $distance, &$searchFlag, &$myLocationFilter) {
                $searchFlag = $searchFlag || TRUE;
                if ($location === 'mylocation' && ! empty($lon) && ! empty($lat)) {
                    $myLocationFilter = TRUE;
                    $store->havingRaw("distance <= {$distance}");
                } else {
                    $store->leftJoin(DB::Raw("
                            (SELECT
                                s.name as s_name,
                                m.city as m_city
                            FROM {$prefix}merchants s
                            LEFT JOIN {$prefix}merchants m
                                ON m.merchant_id = s.parent_id
                                AND m.city = {$this->quote($location)}
                            WHERE s.object_type = 'tenant'
                                AND s.status = 'active'
                                AND m.status = 'active'
                            ) as tmp_city
                        "), DB::Raw("tmp_city.s_name"), '=', 'merchants.name')
                        ->where(DB::Raw("tmp_city.m_city"), $location);
                }
            });

            $_realStore = clone $store;

            $querySql = $store->toSql();
            $store = DB::table(DB::raw("({$querySql}) as subQuery"))->mergeBindings($store->getQuery());

            if ($list_type === "featured") {
                $store->select(DB::raw('subQuery.merchant_id'), 'name', 'description','logo_url', 'mall_id', 'mall_name', 'placement_order',
                        DB::raw("placement_type AS placement_type_orig"),
                            DB::raw("CASE WHEN SUM(
                                        CASE
                                            WHEN (placement_type = 'preferred_list_regular' OR placement_type = 'preferred_list_large')
                                            THEN 1
                                            ELSE 0
                                        END) > 0
                                    THEN 'preferred_list_large'
                                    ELSE placement_type
                                    END AS placement_type"));
            } else {
                $store->select(DB::raw('subQuery.merchant_id'), 'name', 'description','logo_url', 'mall_id', 'mall_name', 'placement_type', 'placement_order');
            }

            if ($sort_by === 'location' && ! empty($lon) && ! empty($lat)) {
                $searchFlag = $searchFlag || TRUE;
                $sort_by = 'distance';
                $store = $store->addSelect(DB::raw("min(distance)"))
                                ->groupBy('name')
                                ->orderBy('placement_order', 'desc')
                                ->orderBy($sort_by, $sort_mode)
                                ->orderBy('name', 'asc');
            } else {
                $store = $store->groupBy('name')
                                ->orderBy('placement_order', 'desc')
                                ->orderBy('name', 'asc');
            }

            // record GTM search activity
            if ($searchFlag) {
                $parameters = [
                    'displayName' => 'Store',
                    'keywords' => OrbitInput::get('keyword', NULL),
                    'categories' => OrbitInput::get('category_id', NULL),
                    'location' => OrbitInput::get('location', NULL),
                    'sortBy' => OrbitInput::get('sortby', 'name')
                ];

                GTMSearchRecorder::create($parameters)->saveActivity($user);
            }

            $_store = clone $store;

            // Cache the result of database calls
            OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($store);

            $recordCounter = RecordCounter::create($_store);
            OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($recordCounter->getQueryBuilder());

            $recordCounterRealStores = RecordCounter::create($_realStore);
            OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($recordCounterRealStores->getQueryBuilder());

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $store->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $store->skip($skip);

            $liststore = $store->get();
            $count = $recordCounter->count();

            // random featured adv
            if ($list_type === 'featured') {
                $featuredStore = $_store->get();
                $advertedCampaigns = array_filter($featuredStore, function($v) {
                    return ($v->placement_type_orig === 'featured_list');
                });

                if (count($advertedCampaigns) > $take) {
                    $random = array();
                    $listSlide = array_rand($advertedCampaigns, $take);

                    if (count($listSlide) > 1) {
                        foreach ($listSlide as $key => $value) {
                            $random[] = $advertedCampaigns[$value];
                        }
                    } else {
                        $random = $advertedCampaigns[$listSlide];
                    }

                    $liststore = $random;
                    $count = count($random);
                } else {
                    $liststore = array_slice($featuredStore, 0, $take);
                    $count = count($liststore);
                }
            } else {
                $take = PaginationNumber::parseTakeFromGet('retailer');
                $store->take($take);

                $skip = PaginationNumber::parseSkipFromGet();
                $store->skip($skip);
            }

            $data = new \stdclass();
            $extras = new \stdClass();
            $extras->total_stores = $count;
            $extras->total_merchants = $recordCounterRealStores->count();

            $data->returned_records = count($liststore);
            $data->total_records = $count;
            $data->extras = $extras;
            $data->records = $liststore;

            // save activity when accessing listing
            // omit save activity if accessed from mall ci campaign list 'from_mall_ci' !== 'y'
            // moved from generic activity number 32
            if (OrbitInput::get('from_homepage', '') !== 'y') {
                if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                    if (is_object($mall)) {
                        $activityNotes = sprintf('Page viewed: View mall store list page');
                        $activity->setUser($user)
                            ->setActivityName('view_mall_store_list')
                            ->setActivityNameLong('View mall store list')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Store')
                            ->setNotes($activityNotes)
                            ->responseOK()
                            ->save();
                    } else {
                        $activityNotes = sprintf('Page viewed: Store list');
                        $activity->setUser($user)
                            ->setActivityName('view_stores_main_page')
                            ->setActivityNameLong('View Stores Main Page')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Store')
                            ->setNotes($activityNotes)
                            ->responseOK()
                            ->save();
                    }
                }
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
            $this->response->data = null;
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * GET - get mall list after click store name
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
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
    public function getMallStoreList()
    {
        $httpCode = 200;
        try {
            $this->checkAuth();

            $sort_by = OrbitInput::get('sortby', 'merchants.name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $storename = OrbitInput::get('store_name');
            $keyword = OrbitInput::get('keyword');

            $validator = Validator::make(
                array(
                    'store_name' => $storename,
                ),
                array(
                    'store_name' => 'required',
                ),
                array(
                    'required' => 'Store name is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            // Query without searching keyword
            $mall = Mall::select('merchants.merchant_id', 'merchants.name', 'merchants.ci_domain', 'merchants.city', 'merchants.description', DB::raw("CONCAT({$prefix}merchants.ci_domain, '/customer/tenant?id=', oms.merchant_id) as store_url"))
                    ->join(DB::raw("(select merchant_id, `name`, parent_id from {$prefix}merchants where name = {$this->quote($storename)} and status = 'active') as oms"), DB::raw('oms.parent_id'), '=', 'merchants.merchant_id')
                    ->active();

            // Query list mall based on keyword. Handling description and keyword can be different with other stores
            if (! empty($keyword)) {
                $words = explode(" ", $keyword);
                $keywordSql = " 1=1 ";
                foreach ($words as $key => $value) {
                    if (strlen($value) === 1 && $value === '%') {
                        $keywordSql .= " or {$prefix}merchants.name like '%|{$value}%' escape '|' or {$prefix}merchants.description like '%|{$value}%' escape '|' or {$prefix}keywords.keyword like '%|{$value}%' escape '|' ";
                    } else {
                        // escaping the query
                        $word = '%' . $value . '%';
                        $value = $this->quote($word);
                        $keywordSql .= " or {$prefix}merchants.name like {$value} or {$prefix}merchants.description like {$value} or {$prefix}keywords.keyword like {$value} ";
                    }
                }

                $mall = Mall::select('merchants.merchant_id', 'merchants.name', 'merchants.ci_domain', 'merchants.city', 'merchants.description', DB::raw("CONCAT({$prefix}merchants.ci_domain, '/customer/tenant?id=', oms.merchant_id) as store_url"))
                        ->join(DB::raw("( select {$prefix}merchants.merchant_id, name, parent_id from {$prefix}merchants
                                            left join {$prefix}keyword_object on {$prefix}merchants.merchant_id = {$prefix}keyword_object.object_id
                                            left join {$prefix}keywords on {$prefix}keyword_object.keyword_id = {$prefix}keywords.keyword_id
                                            where name = {$this->quote($storename)}
                                            and {$prefix}merchants.status = 'active'
                                            and (" . $keywordSql . ")
                                        ) as oms"), DB::raw('oms.parent_id'), '=', 'merchants.merchant_id')
                        ->active();
            }

            $mall = $mall->groupBy('merchants.merchant_id')->orderBy($sort_by, $sort_mode);

            $_mall = clone $mall;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $mall->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $mall->skip($skip);

            $listmall = $mall->get();
            $count = RecordCounter::create($_mall)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listmall);
            $this->response->data->records = $listmall;
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

    /**
     * GET - get all detail store in all mall, group by name
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getStoreDetail()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $mall = NULL;

        try {
            $this->checkAuth();
            $user = $this->api->user;

            $storename = OrbitInput::get('store_name');
            $language = OrbitInput::get('language', 'id');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'store_name' => $storename,
                    'language' => $language,
                ),
                array(
                    'store_name' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                ),
                array(
                    'required' => 'Store name is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();

            $store = Tenant::select(
                                'merchants.merchant_id',
                                'merchants.name',
                                DB::Raw("CASE WHEN (
                                                select mt.description
                                                from {$prefix}merchant_translations mt
                                                where mt.merchant_id = {$prefix}merchants.merchant_id
                                                    and mt.merchant_language_id = {$this->quote($valid_language->language_id)}
                                            ) = ''
                                            THEN {$prefix}merchants.description
                                            ELSE (
                                                select mt.description
                                                from {$prefix}merchant_translations mt
                                                where mt.merchant_id = {$prefix}merchants.merchant_id
                                                    and mt.merchant_language_id = {$this->quote($valid_language->language_id)}
                                            )
                                        END as description
                                    "),
                                'merchants.url'
                            )
                ->with(['categories' => function ($q) use ($valid_language, $prefix) {
                        $q->select(
                                DB::Raw("
                                        CASE WHEN (
                                                    SELECT ct.category_name
                                                    FROM {$prefix}category_translations ct
                                                        WHERE ct.status = 'active'
                                                            and ct.merchant_language_id = {$this->quote($valid_language->language_id)}
                                                            and ct.category_id = {$prefix}categories.category_id
                                                    ) != ''
                                            THEN (
                                                    SELECT ct.category_name
                                                    FROM {$prefix}category_translations ct
                                                    WHERE ct.status = 'active'
                                                        and ct.merchant_language_id = {$this->quote($valid_language->language_id)}
                                                        and category_id = {$prefix}categories.category_id
                                                    )
                                            ELSE {$prefix}categories.category_name
                                        END AS category_name
                                    ")
                            )
                            ->groupBy('categories.category_id')
                            ->orderBy('category_name')
                            ;
                    }, 'mediaLogo' => function ($q) {
                        $q->select(
                                'media.path',
                                'media.object_id'
                            );
                    }, 'mediaImageOrig' => function ($q) {
                        $q->select(
                                'media.path',
                                'media.object_id'
                            );
                    }, 'mediaImageCroppedDefault' => function ($q) {
                        $q->select(
                                'media.path',
                                'media.object_id'
                            );
                    }])
                ->join(DB::raw("(select merchant_id, status, parent_id from {$prefix}merchants where object_type = 'mall') as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                ->where('merchants.status', 'active')
                ->whereRaw("oms.status = 'active'")
                ->where('merchants.name', $storename);

            OrbitInput::get('mall_id', function($mallId) use ($store, &$mall, $prefix) {
                $store->where('merchants.parent_id', $mallId);
                $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $mallId)
                        ->first();
            });

            $store = $store->orderBy('merchants.created_at', 'asc')
                ->first();

            if (is_object($mall)) {
                $activityNotes = sprintf('Page viewed: View mall store detail page');
                $activity->setUser($user)
                    ->setActivityName('view_mall_store_detail')
                    ->setActivityNameLong('View mall store detail')
                    ->setObject($store)
                    ->setLocation($mall)
                    ->setModuleName('Store')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
                $activityNotes = sprintf('Page viewed: Landing Page Store Detail Page');
                $activity->setUser($user)
                    ->setActivityName('view_landing_page_store_detail')
                    ->setActivityNameLong('View GoToMalls Store Detail')
                    ->setObject($store)
                    ->setLocation($mall)
                    ->setModuleName('Store')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $this->response->data = $store;
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

    /**
     * GET - get mall detail list after click store name
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
    public function getMallDetailStore()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;

        try {
            $this->checkAuth();
            $user = $this->api->user;

            $sort_by = OrbitInput::get('sortby', 'merchants.name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $storename = OrbitInput::get('store_name');
            $keyword = OrbitInput::get('keyword');

            $validator = Validator::make(
                array(
                    'store_name' => $storename,
                ),
                array(
                    'store_name' => 'required',
                ),
                array(
                    'required' => 'Store name is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            // Query without searching keyword
            $mall = Mall::select('merchants.merchant_id',
                                    'merchants.name',
                                    'merchants.ci_domain',
                                    'merchants.city',
                                    'merchants.description',
                                    DB::raw("CONCAT({$prefix}merchants.ci_domain, '/customer/tenant?id=', oms.merchant_id) as store_url"))
                    ->with(['tenants' => function ($q) use ($prefix, $storename) {
                            $q->select('merchants.merchant_id',
                                        'merchants.name as title',
                                        'merchants.phone',
                                        'merchants.url',
                                        'merchants.description',
                                        'merchants.parent_id',
                                        DB::raw("(CASE WHEN unit = '' THEN {$prefix}objects.object_name ELSE CONCAT({$prefix}objects.object_name, \" - \", unit) END) AS location")
                                    )
                              ->join('objects', 'objects.object_id', '=', 'merchants.floor_id')
                              ->where('objects.object_type', 'floor')
                              ->where('merchants.name', $storename)
                              ->where('merchants.status', 'active')
                              ->with(['categories' => function ($q) {
                                    $q->select(
                                            'category_name'
                                        );
                                }, 'mediaMap' => function ($q) {
                                    $q->select(
                                            'media.object_id',
                                            'media.path'
                                        );
                                }]);
                        }, 'mediaLogo' => function ($q) {
                                    $q->select(
                                            'media.object_id',
                                            'media.path'
                                        );
                        }]);

            // Query list mall based on keyword. Handling description and keyword can be different with other stores
            if (! empty($keyword)) {
                $words = explode(" ", $keyword);
                $keywordSql = " 1=1 ";
                foreach ($words as $key => $value) {
                    if (strlen($value) === 1 && $value === '%') {
                        $keywordSql .= " or {$prefix}merchants.name like '%|{$value}%' escape '|' or {$prefix}merchants.description like '%|{$value}%' escape '|' or {$prefix}keywords.keyword like '%|{$value}%' escape '|' ";
                    } else {
                        // escaping the query
                        $word = '%' . $value . '%';
                        $value = $this->quote($word);
                        $keywordSql .= " or {$prefix}merchants.name like {$value} or {$prefix}merchants.description like {$value} or {$prefix}keywords.keyword like {$value} ";
                    }
                }

                $mall = $mall->join(DB::raw("( select {$prefix}merchants.merchant_id, name, parent_id from {$prefix}merchants
                                            left join {$prefix}keyword_object on {$prefix}merchants.merchant_id = {$prefix}keyword_object.object_id
                                            left join {$prefix}keywords on {$prefix}keyword_object.keyword_id = {$prefix}keywords.keyword_id
                                            where name = {$this->quote($storename)}
                                            and {$prefix}merchants.status = 'active'
                                            and (" . $keywordSql . ")
                                        ) as oms"), DB::raw('oms.parent_id'), '=', 'merchants.merchant_id')
                            ->active();
            } else {
                $mall = $mall->join(DB::raw("(select merchant_id, `name`, parent_id from {$prefix}merchants where name = {$this->quote($storename)} and status = 'active') as oms"), DB::raw('oms.parent_id'), '=', 'merchants.merchant_id')
                            ->active();
            }

            $mall = $mall->groupBy('merchants.merchant_id')->orderBy($sort_by, $sort_mode);

            $_mall = clone $mall;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $mall->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $mall->skip($skip);

            // moved from generic activity number 40
            if (empty($skip)) {
                $activityNotes = sprintf('Page viewed: Store location list');
                $activity->setUser($user)
                    ->setActivityName('view_store_location')
                    ->setActivityNameLong('View Store Location Page')
                    ->setObject(null)
                    ->setObjectDisplayName($storename)
                    ->setModuleName('Store')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $listmall = $mall->get();
            $count = RecordCounter::create($_mall)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listmall);
            $this->response->data->records = $listmall;
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
            $this->checkAuth();

            $sort_by = OrbitInput::get('sortby', 'campaign_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $store_name = OrbitInput::get('store_name');
            $keyword = OrbitInput::get('keyword');
            $language = OrbitInput::get('language', 'id');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'store_name' => $store_name,
                    'language' => $language,
                ),
                array(
                    'store_name' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                ),
                array(
                    'required' => 'Store name is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();

            // get news list
            $news = DB::table('news')->select(
                        'news.news_id as campaign_id',
                        DB::Raw("
                                 CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN {$prefix}news.news_name ELSE {$prefix}news_translations.news_name END as campaign_name
                            "),
                        'news.object_type as campaign_type',
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
                                        AND om.name = '{$store_name}'
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
                                CASE WHEN {$prefix}media.path is null THEN (
                                        select m.path
                                        from {$prefix}news_translations nt
                                        join {$prefix}media m
                                            on m.object_id = nt.news_translation_id
                                            and m.media_name_long = 'news_translation_image_orig'
                                        where nt.news_id = {$prefix}news.news_id
                                        group by nt.news_id
                                    ) ELSE {$prefix}media.path END as original_media_path
                            "))
                        ->leftJoin('news_translations', function ($q) use ($valid_language) {
                            $q->on('news_translations.news_id', '=', 'news.news_id')
                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })
                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('media', function ($q) {
                            $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                            $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                        })
                        ->where('merchants.name', $store_name)
                        ->where('news.object_type', '=', 'news')
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->groupBy('campaign_id')
                        ->orderBy('news.created_at', 'desc');

            $promotions = DB::table('news')->select(
                        'news.news_id as campaign_id',
                        DB::Raw("
                                CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN {$prefix}news.news_name ELSE {$prefix}news_translations.news_name END as campaign_name
                        "),
                        'news.object_type as campaign_type',
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
                                CASE WHEN {$prefix}media.path is null THEN (
                                        select m.path
                                        from {$prefix}news_translations nt
                                        join {$prefix}media m
                                            on m.object_id = nt.news_translation_id
                                            and m.media_name_long = 'news_translation_image_orig'
                                        where nt.news_id = {$prefix}news.news_id
                                        group by nt.news_id
                                    ) ELSE {$prefix}media.path END as original_media_path
                            "))
                        ->leftJoin('news_translations', function ($q) use ($valid_language) {
                            $q->on('news_translations.news_id', '=', 'news.news_id')
                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })

                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('media', function ($q) {
                            $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                            $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                        })
                        ->where('merchants.name', $store_name)
                        ->where('news.object_type', '=', 'promotion')
                        ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                        ->groupBy('campaign_id')
                        ->orderBy('news.created_at', 'desc');

            // get coupon list
            $coupons = DB::table('promotions')->select(DB::raw("
                                {$prefix}promotions.promotion_id as campaign_id,
                                CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN {$prefix}promotions.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as campaign_name,
                                'coupon' as campaign_type,
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
                                CASE WHEN {$prefix}media.path is null THEN (
                                        select m.path
                                        from {$prefix}coupon_translations ct
                                        join {$prefix}media m
                                            on m.object_id = ct.coupon_translation_id
                                            and m.media_name_long = 'coupon_translation_image_orig'
                                        where ct.promotion_id = {$prefix}promotions.promotion_id
                                        group by ct.promotion_id
                                    ) ELSE {$prefix}media.path END as original_media_path
                            "))
                            ->leftJoin('promotion_rules', 'promotion_rules.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                            ->leftJoin('coupon_translations', function ($q) use ($valid_language) {
                                $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                                  ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                            })
                            ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                            ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                            ->leftJoin('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id')
                            ->leftJoin('media', function($q) {
                                $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                                $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                            })
                            ->leftJoin(DB::raw("(SELECT promotion_id, COUNT(*) as tot FROM {$prefix}issued_coupons WHERE status = 'available' GROUP BY promotion_id) as available"), DB::raw("available.promotion_id"), '=', 'promotions.promotion_id')
                            ->whereRaw("available.tot > 0")
                            ->whereRaw("{$prefix}promotion_rules.rule_type != 'blast_via_sms'")
                            ->where('merchants.name', $store_name)
                            ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                            ->groupBy('campaign_id')
                            ->orderBy(DB::raw("{$prefix}promotions.created_at"), 'desc');

            $result = $news->unionAll($promotions)->unionAll($coupons);

            $querySql = $result->toSql();

            $campaign = DB::table(DB::Raw("({$querySql}) as campaign"))->mergeBindings($result);

            $_campaign = clone $campaign;

            $take = PaginationNumber::parseTakeFromGet('campaign');

            $campaign->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $campaign->skip($skip);

            $campaign->orderBy($sort_by, $sort_mode);

            $listcampaign = $campaign->get();

            $this->response->data = new stdClass();
            $this->response->data->total_records = count($_campaign->get());
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
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
