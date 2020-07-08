<?php namespace Orbit\Controller\API\v1\Pub\Store;
/**
 * An API controller for get store location list in mall
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
use Orbit\Helper\Util\SimpleCache;
use Config;
use Mall;
use Tenant;
use stdClass;
use DB;
use Validator;
use Language;
use Activity;
use Lang;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Country;

class StoreMallDetailAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $store = NULL;
    protected $withoutScore = FALSE;

    /**
     * GET - get store location list in mall
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
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
        $storename = null;

        $cacheKey = [];
        $serializedCacheKey = [];

        // Cache result of all possible calls to backend storage
        $cacheConfig = Config::get('orbit.cache.context');
        $cacheContext = 'location-store-list';

        $recordCache = SimpleCache::create($cacheConfig, $cacheContext);
        $totalRecordCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-total-rec');
        $numberOfMallRecordCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-total-mall');


        try {
            $user = $this->getUser();
            $mallId = OrbitInput::get('mall_id', null);
            $is_mall = OrbitInput::get('is_mall', 'n');
            $merchantId = OrbitInput::get('merchant_id');
            $location = (array) OrbitInput::get('location', []);
            $cities = (array) OrbitInput::get('cities', []);
            $noActivity = OrbitInput::get('no_activity', null);
            $mongoConfig = Config::get('database.mongodb');
            $country = OrbitInput::get('country', null);
            $ul = OrbitInput::get('ul', null);
            $take = PaginationNumber::parseTakeFromGet('retailer');
            $skip = PaginationNumber::parseSkipFromGet();

            $skipMall = OrbitInput::get('skip_mall', 'N');

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchantId,
                    'skip_mall' => $skipMall,
                ),
                array(
                    'merchant_id' => 'required',
                    'skip_mall' => 'in:Y,N',
                ),
                array(
                    'required' => 'Merchant id is required',
                )
            );

            // Pass all possible parameters to be used as cache key.
            // Make sure there is no missing one.
            $cacheKey = [
                'mall_id' => $mallId,
                'is_mall' => $is_mall,
                'merchant_id' => $merchantId,
                'location' => $location,
                'cities' => $cities,
                'take' => $take,
                'skip' => $skip,
                'skip_mall' => $skipMall,
            ];

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $mallLogo = "CONCAT({$this->quote($urlPrefix)}, img.path) as location_logo";
            $mallMap = "CONCAT({$this->quote($urlPrefix)}, map.path) as map_image";
            if ($usingCdn) {
                $mallLogo = "CASE WHEN (img.cdn_url is null or img.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, img.path) ELSE img.cdn_url END as location_logo";
                $mallMap = "CASE WHEN (map.cdn_url is null or map.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, map.path) ELSE map.cdn_url END as map_image";
            }

            $image = "CONCAT({$this->quote($urlPrefix)}, m.path) as path";
            if ($usingCdn) {
                $image = "CASE WHEN (m.cdn_url is null or m.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END as path";
            }

            // Get store name base in merchant_id
            $store = Tenant::select('merchants.merchant_id', 'merchants.name', DB::raw('oms.country_id'),
                            DB::Raw("
                                    (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'retailer_logo_orig'
                                    AND m.object_id = {$prefix}merchants.merchant_id limit 1) AS original_media_path
                            ")
                        )
                        ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                        ->where('merchants.merchant_id', $merchantId)
                        ->where('merchants.status', '=', 'active')
                        ->where(DB::raw('oms.status'), '=', 'active')
                        ->first();

            $countryId = '';
            $getCountry = Country::where('name', '=', $country)->first();
            if ($getCountry) {
                $countryId = $getCountry->country_id;
            }
            if (! empty($store)) {
                $storename = $store->name;
                if ($countryId === '') {
                    $countryId = $store->country_id;
                }
            }

            $mall = Tenant::select(
                                    'merchants.merchant_id',
                                    DB::raw("mall.merchant_id as mall_id"),
                                    DB::raw("mall.city"),
                                    'merchants.name',
                                    DB::raw("mall.name as mall_name"),
                                    DB::raw("mall.address_line1 as address"),
                                    DB::raw("mall.operating_hours"),
                                    DB::raw("mall.is_subscribed"),
                                    DB::raw("mall.object_type as location_type"),
                                    DB::raw("{$mallLogo}"),
                                    DB::raw("{$mallMap}"),
                                    DB::raw("GROUP_CONCAT( {$prefix}merchants.floor SEPARATOR '||') as floor"),
                                    DB::raw("GROUP_CONCAT( {$prefix}merchants.unit SEPARATOR '||') as unit"),
                                    DB::raw("GROUP_CONCAT( {$prefix}merchants.phone SEPARATOR '||') as phone"),
                                    DB::raw("mall.phone as mall_phone"),
                                    DB::raw("x(position) as latitude"),
                                    DB::raw("y(position) as longitude")
                                )
                                // Floor of store
                                ->leftJoin('objects', function($q){
                                    $q->on('objects.object_id', '=', 'merchants.floor_id')
                                         ->where('objects.object_type', '=', 'floor');
                                })
                                // Mall of tenant
                                ->leftJoin(DB::raw("{$prefix}merchants as mall"), DB::Raw("mall.merchant_id"), '=', 'merchants.parent_id')
                                // Merchant Geofences
                                ->leftJoin('merchant_geofences', DB::Raw("mall.merchant_id"), '=', 'merchant_geofences.merchant_id')
                                // Map of store
                                ->leftJoin(DB::raw("{$prefix}media as map"), function($q) use ($prefix){
                                    $q->on(DB::raw('map.object_id'), '=',  'merchants.merchant_id')
                                      ->on(DB::raw('map.media_name_long'), 'IN', DB::raw("('mall_map_orig', 'retailer_map_orig', 'retailer_storemap_orig')"))
                                      ;
                                })
                                // Logo of mall
                                ->leftJoin(DB::raw("{$prefix}media as img"), function($q) use ($prefix){
                                    $q->on(DB::raw('img.object_id'), '=',  DB::raw("mall.merchant_id"))
                                      ->on(DB::raw('img.media_name_long'), 'IN', DB::raw("('mall_logo_orig', 'retailer_logo_orig')"))
                                      ;
                                })
                              ->where('merchants.name', $storename)
                              ->where('merchants.status', 'active')
                              ->where(DB::raw("mall.country_id"), '=', $countryId)
                              ->where(DB::raw("mall.status"), 'active')
                              ->where(DB::raw("mall.is_subscribed"), 'Y');

            if (!empty ($cities)) {
                $mall->whereIn(DB::raw('mall.city'), $cities);
            }

            // get number of mall without filter
            $numberOfMall = 0;
            $_numberOfMall = clone $mall;
            $_numberOfMall->groupBy(DB::raw("merchant_id"))->get();

            if (! empty($location)) {
                if (! in_array('0', $location)) {
                    $mall->whereIn(DB::raw('mall.city'), $location);
                }
            } else {
                if ($is_mall !== 'y' && ! empty($cities)) {
                    // filter by cities
                    if (! in_array('0', $cities)) {
                        $mall->whereIn(DB::raw('mall.city'), $cities);
                    }
                }
            }

            if ($skipMall === 'Y') {
                if (! empty($mallId)) {
                    $mall->where(DB::raw("mall.merchant_id"), '!=', $mallId);
                }
            } else {
                if (! empty($mallId)) {
                    $mall->where(DB::raw("mall.merchant_id"), '=', $mallId);
                }
            }

            // Order data city alphabetical
            $mall->orderBy(DB::raw('mall.city'), 'asc');
            $mall->orderBy('merchants.name', 'asc');

            $mall = $mall->groupBy(DB::raw("merchant_id"));

            $_mall = clone $mall;
            $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);
            $recordCounter = RecordCounter::create($_mall);

            // Try to get the result from cache
            $totalRec = $totalRecordCache->get($serializedCacheKey, function() use ($recordCounter) {
                return $recordCounter->count();
            });

            // Put the result in cache if it is applicable
            $totalRecordCache->put($serializedCacheKey, $totalRec);

            $numberOfMallRecordCounter = RecordCounter::create($_numberOfMall);
            // Try to get the result from cache
            $numberOfMall = $numberOfMallRecordCache->get($serializedCacheKey, function() use ($numberOfMallRecordCounter) {
                return $numberOfMallRecordCounter->count();
            });

            // Put the result in cache if it is applicable
            $numberOfMallRecordCache->put($serializedCacheKey, $numberOfMall);

            $mall->take($take);
            $mall->skip($skip);

            // Try to get the result from cache
            $listOfRec = $recordCache->get($serializedCacheKey, function() use ($mall) {
                return $mall->get();
            });
            $recordCache->put($serializedCacheKey, $listOfRec);

            // ---- START RATING ----
            $locationIds = [];
            foreach ($listOfRec as &$itemLocation) {
                $locationIds[] = $itemLocation->mall_id;
                $itemLocation->rating_average = null;
                $itemLocation->review_counter = null;
            }

            $objectIds = [];
            $storeIdList = Tenant::select('merchants.merchant_id')
                            ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                            ->where('merchants.status', '=', 'active')
                            ->where(DB::raw('oms.status'), '=', 'active')
                            ->where('merchants.name', $storename)
                            ->where(DB::raw("oms.country_id"), $countryId)
                            ->get();

            foreach ($storeIdList as $storeId) {
                $objectIds[] = $storeId->merchant_id;
            }

            $arrayQuery = 'object_id[]=' . implode('&object_id[]=', $objectIds);

            $queryString = [
                'object_type' => 'store',
                'location_id' => $locationIds
            ];

            $mongoClient = MongoClient::create($mongoConfig);
            $endPoint = "reviews?" . $arrayQuery;
            $response = $mongoClient->setCustomQuery(TRUE)
                                    ->setQueryString($queryString)
                                    ->setEndPoint($endPoint)
                                    ->request('GET');

            $reviewList = $response->data;

            $ratings = array();
            foreach ($reviewList->records as $review) {
                $locationId = $review->location_id;
                $ratings[$locationId]['rating'] = (! empty($ratings[$locationId]['rating'])) ? $ratings[$locationId]['rating'] + $review->rating : $review->rating;
                $ratings[$locationId]['totalReview'] = (! empty($ratings[$locationId]['totalReview'])) ? $ratings[$locationId]['totalReview'] + 1 : 1;

                $ratings[$locationId]['average'] = $ratings[$locationId]['rating'] / $ratings[$locationId]['totalReview'];
            }

            foreach ($listOfRec as &$itemLocation) {
                $mallId = $itemLocation->mall_id;
                $ratingAverage = (! empty($ratings[$mallId]['average'])) ? number_format(round($ratings[$mallId]['average'], 1), 1) : null;
                $reviewCounter = (! empty($ratings[$mallId]['totalReview'])) ? $ratings[$mallId]['totalReview'] : null;

                $itemLocation->rating_average = $ratingAverage;
                $itemLocation->review_counter = $reviewCounter;
            }

            // moved from generic activity number 40
            if (strtoupper($noActivity) != 'Y') {
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
            }

            $data = new \stdClass();
            $data->returned_records = count($listOfRec);
            $data->total_records = $totalRec;
            $data->total_malls = $numberOfMall;
            if (is_object($store)) {
                $data->store_name = $store->name;
                $data->original_media_path = $store->original_media_path;
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

        $output = $this->render($httpCode);

        return $output;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
