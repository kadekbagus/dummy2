<?php namespace Orbit\Controller\API\v1\Pub\Coupon;
/**
 * Controller for Coupon location list.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Lang;
use \Exception;
use PromotionRetailer;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Activity;
use Coupon;
use Mall;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\MongoDB\Client as MongoClient;

class CouponLocationAPIController extends PubControllerAPI
{

    /**
     * GET - get list location of coupon
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string coupon_id
     * @param string sortby
     * @param string sortmode
     * @param string mall_id
     * @param string is_detail
     * @param string location
     * @param string orbit.geo_location.distance
     * @param string ul
     * @param string take
     * @param string skip
     *
     * @return Illuminate\Support\Facades\Response
     */

    public function getCouponLocations()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;
        $mall = null;

        $cacheKey = [];
        $serializedCacheKey = [];

        // Cache result of all possible calls to backend storage
        $cacheConfig = Config::get('orbit.cache.context');
        $cacheContext = 'location-coupon-list';

        $recordCache = SimpleCache::create($cacheConfig, $cacheContext);
        $totalRecordCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-total-rec');

        try{
            $user = $this->getUser();

            $coupon_id = OrbitInput::get('coupon_id', null);
            $mall_id = OrbitInput::get('mall_id', null);
            $is_detail = OrbitInput::get('is_detail', 'n');
            $is_mall = OrbitInput::get('is_mall', 'n');
            $location = (array) OrbitInput::get('location', []);
            $country = OrbitInput::get('country');
            $cities = (array) OrbitInput::get('cities', []);
            $distance = Config::get('orbit.geo_location.distance', 10);
            $ul = OrbitInput::get('ul', null);
            $language = OrbitInput::get('language', 'id');
            $take = PaginationNumber::parseTakeFromGet('promotions');
            $skip = PaginationNumber::parseSkipFromGet();
            $withCache = TRUE;
            $skipMall = OrbitInput::get('skip_mall', 'N');
            $storeName = OrbitInput::get('store_name');
            $mongoConfig = Config::get('database.mongodb');

            // need to handle request for grouping by name_orig and order by name_orig and city
            $sortBy = OrbitInput::get('sort_by');

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();
            $validator = Validator::make(
                array(
                    'coupon_id' => $coupon_id,
                    'language' => $language,
                    'sort_by' => $sortBy,
                    'skip_mall' => $skipMall,
                ),
                array(
                    'coupon_id' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                    'sort_by' => 'in:city',
                    'skip_mall' => 'in:Y,N',
                ),
                array(
                    'required' => 'Coupon ID is required',
                )
            );

            // Pass all possible parameters to be used as cache key.
            // Make sure there is no missing one.
            $cacheKey = [
                'coupon_id' => $coupon_id,
                'mall_id' => $mall_id,
                'is_detail' => $is_detail,
                'is_mall' => $is_mall,
                'location' => $location,
                'country' => $country,
                'cities' => $cities,
                'distance' => $distance,
                'mall' => $mall,
                'take' => $take,
                'skip' => $skip,
                'sort_by' => $sortBy,
                'skip_mall' => $skipMall,
                'store_name' => $storeName,
            ];

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $couponHelper->getValidLanguage();

            if (! empty($mall_id)) {
                $mall = Mall::where('merchant_id', '=', $mall_id)->first();
            }

            $prefix = DB::getTablePrefix();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $mallLogo = "CONCAT({$this->quote($urlPrefix)}, img.path) as location_logo";
            $locationLogo = "CONCAT({$this->quote($urlPrefix)}, img_loc.path) as location_logo_orig";
            $mallMap = "CONCAT({$this->quote($urlPrefix)}, map.path) as map_image";
            if ($usingCdn) {
                $mallLogo = "CASE WHEN (img.cdn_url is null or img.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, img.path) ELSE img.cdn_url END as location_logo";
                $locationLogo = "CASE WHEN (img_loc.cdn_url is null or img_loc.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, img_loc.path) ELSE img_loc.cdn_url END as location_logo_orig";
                $mallMap = "CASE WHEN (map.cdn_url is null or map.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, map.path) ELSE map.cdn_url END as map_image";
            }

            $couponLocations = PromotionRetailer::select(
                                            DB::raw("{$prefix}merchants.merchant_id as merchant_id"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.parent_id ELSE {$prefix}merchants.merchant_id END as mall_id"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN CONCAT({$prefix}merchants.name, ' at ', oms.name) ELSE CONCAT('Customer Service at ', {$prefix}merchants.name) END as name"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.name ELSE {$prefix}merchants.name END as mall_name"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END as city"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.address_line1 ELSE {$prefix}merchants.address_line1 END as address"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.operating_hours ELSE {$prefix}merchants.operating_hours END as operating_hours"),
                                            DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.is_subscribed ELSE {$prefix}merchants.is_subscribed END as is_subscribed"),
                                            DB::raw("{$prefix}merchants.object_type as location_type"),
                                            DB::raw("{$mallLogo}"),
                                            DB::raw("{$locationLogo}"),
                                            DB::raw("{$mallMap}"),
                                            DB::raw("{$prefix}merchants.name as name_orig"),
                                            DB::raw("GROUP_CONCAT( CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.floor ELSE '' END SEPARATOR '||') as floor"),
                                            DB::raw("GROUP_CONCAT( CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.unit ELSE '' END SEPARATOR '||') as unit"),
                                            DB::raw("GROUP_CONCAT( {$prefix}merchants.phone SEPARATOR '||') as phone"),
                                            DB::raw("oms.phone as mall_phone"),
                                            DB::raw("x(position) as latitude"),
                                            DB::raw("y(position) as longitude")
                                        )
                                    ->leftJoin('promotions', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->leftJoin('merchant_geofences', 'merchant_geofences.merchant_id', '=', DB::raw("IF({$prefix}merchants.object_type = 'tenant', {$prefix}merchants.parent_id, {$prefix}merchants.merchant_id)"))

                                    // Map
                                    ->leftJoin(DB::raw("{$prefix}media as map"), function($q) use ($prefix){
                                        $q->on(DB::raw('map.object_id'), '=', "merchants.merchant_id")
                                          ->on(DB::raw('map.media_name_long'), 'IN', DB::raw("('mall_map_orig', 'retailer_map_orig', 'retailer_storemap_orig')"));
                                    })
                                    // Mall Logo
                                    ->leftJoin(DB::raw("{$prefix}media as img"), function($q) use ($prefix) {
                                        $q->on(DB::raw('img.object_id'), '=', DB::Raw("
                                                        (select CASE WHEN t.object_type = 'tenant'
                                                                    THEN m.merchant_id
                                                                    ELSE t.merchant_id
                                                                END as mall_id
                                                        from orb_merchants t
                                                        join orb_merchants m
                                                            on m.merchant_id = t.parent_id
                                                        where t.merchant_id = {$prefix}merchants.merchant_id)
                                            "))
                                            ->on(DB::raw('img.media_name_long'), 'IN', DB::raw("('mall_logo_orig', 'retailer_logo_orig')"));
                                    })
                                     // Location Logo
                                    ->leftJoin(DB::raw("{$prefix}media as img_loc"), function($q) use ($prefix){
                                        $q->on(DB::raw('img_loc.object_id'), '=', 'merchants.merchant_id')
                                          ->on(DB::raw('img_loc.media_name_long'), 'IN', DB::raw("('mall_logo_orig', 'retailer_logo_orig')"));
                                    })
                                    ->where('promotions.promotion_id', $coupon_id)
                                    ->where('merchants.status', '=', 'active');

            if ($skipMall === 'Y') {
                // filter news skip by mall id
                OrbitInput::get('mall_id', function($mallid) use ($couponLocations, &$group_by) {
                    $couponLocations->havingRaw("mall_id != '{$mallid}'");
                });
            } else {
                // filter news by mall id
                OrbitInput::get('mall_id', function($mallid) use ($couponLocations, &$group_by) {
                    $couponLocations->where(function($q) use ($mallid){
                                        $q->where('merchants.parent_id', '=', $mallid)
                                          ->orWhere('merchants.merchant_id', '=', $mallid);
                                    });
                });
            }

            // Get user location
            $position = isset($ul)?explode("|", $ul):null;
            $lon = isset($position[0])?$position[0]:null;
            $lat = isset($position[1])?$position[1]:null;

            OrbitInput::get('country', function($country) use ($couponLocations, $prefix) {
                    $couponLocations->where(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.country ELSE {$prefix}merchants.country END)"), $country);
            });

            // Filter by location
            if (! empty($location)) {
                if ($location == 'mylocation' && ! empty($lon) && ! empty($lat)) {
                    $withCache = FALSE;
                    $couponLocations->addSelect(DB::raw("6371 * acos( cos( radians({$lat}) ) * cos( radians( x({$prefix}merchant_geofences.position) ) ) * cos( radians( y({$prefix}merchant_geofences.position) ) - radians({$lon}) ) + sin( radians({$lat}) ) * sin( radians( x({$prefix}merchant_geofences.position) ) ) ) AS distance"))
                                        ->havingRaw("distance <= {$distance}");
                } else {
                    if (! in_array('0', $location)) {
                        $couponLocations->whereIn(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END)"), $location);
                    }
                }
            } else {
                if ($is_mall !== 'y' && ! empty($cities)) { // handle all location from mall level
                    // filter by cities
                    if (! in_array('0', $cities)) {
                        $couponLocations->whereIn(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END)"), $cities);
                    }
                }
            }

            // Order data by nearby or city alphabetical
            if ($location == 'mylocation' && ! empty($lon) && ! empty($lat)) {
                $withCache = FALSE;
                $couponLocations->orderBy('distance', 'asc');
            } else {
                if (! empty($sortBy)) {
                    if ($sortBy === 'city') {
                        $couponLocations->orderBy('city', 'asc')
                            ->orderBy('name_orig', 'asc');
                    }
                } else {
                    $couponLocations->orderBy('name', 'asc');
                }
            }

            OrbitInput::get('store_name', function($storeName) use ($couponLocations) {
                $couponLocations->having('name_orig', '=', $storeName);
            });

            $couponLocations->groupBy(DB::raw('name'));

            $_couponLocations = clone($couponLocations);

            $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);

            $recordCounter = RecordCounter::create($_couponLocations);

            if ($withCache) {
                $totalRec = $totalRecordCache->get($serializedCacheKey, function() use ($recordCounter) {
                    return $recordCounter->count();
                });
                $totalRecordCache->put($serializedCacheKey, $totalRec);
            } else {
                $totalRec = $recordCounter->count();
            }

            $couponLocations->take($take);
            $couponLocations->skip($skip);

            // Try to get the result from cache
            if ($withCache) {
                $listOfRec = $recordCache->get($serializedCacheKey, function() use ($couponLocations) {
                    return $couponLocations->get();
                });
                $recordCache->put($serializedCacheKey, $listOfRec);
            } else {
                $listOfRec = $couponLocations->get();
            }

            // ---- START RATING ----
            if (count($listOfRec) !== 0) {
                $locationIds = [];
                $merchantIds = [];
                foreach ($listOfRec as &$itemLocation) {
                    $locationIds[] = $itemLocation->mall_id;
                    $merchantIds[] = $itemLocation->merchant_id;
                    $itemLocation->rating_average = null;
                    $itemLocation->review_counter = null;
                }

                $queryString = [
                    'object_id'   => $coupon_id,
                    'object_type' => 'coupon',
                    'location_id' => $locationIds
                ];

                if (! empty($storeName)) {
                    $queryString['store_id'] = $merchantIds;
                }

                $mongoClient = MongoClient::create($mongoConfig);
                $endPoint = "reviews";
                $response = $mongoClient->setQueryString($queryString)
                                        ->setEndPoint($endPoint)
                                        ->request('GET');

                $reviewList = $response->data;

                $ratings = array();
                foreach ($reviewList->records as $review) {
                    $locationId = isset($review->location_id) ? $review->location_id : '';
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
            }
            // ---- END OF RATING ----

            $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
            if ($usingCdn) {
                $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
            }

            $couponName = Coupon::select(DB::Raw("
                                    CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as coupon_name,
                                    CASE WHEN (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = {$prefix}coupon_translations.coupon_translation_id
                                        AND {$prefix}coupon_translations.merchant_language_id = {$this->quote($valid_language->language_id)} LIMIT 1) is null
                                    THEN
                                        (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = default_translation.coupon_translation_id
                                        AND default_translation.merchant_language_id = {$this->quote($valid_language->language_id)} LIMIT 1)
                                    ELSE
                                        (SELECT {$image}
                                        FROM orb_media m
                                        WHERE m.media_name_long = 'coupon_translation_image_orig'
                                        AND m.object_id = {$prefix}coupon_translations.coupon_translation_id
                                        AND {$prefix}coupon_translations.merchant_language_id = {$this->quote($valid_language->language_id)} LIMIT 1)
                                    END AS original_media_path
                                "),
                                DB::raw("default_translation.promotion_name as default_name")
                            )
                            ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                            ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                            ->leftJoin('coupon_translations', function ($q) use ($valid_language) {
                                $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                                  ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                            })
                            ->leftJoin('coupon_translations as default_translation', function ($q) {
                                $q->on(DB::raw('default_translation.promotion_id'), '=', 'promotions.promotion_id')
                                  ->on(DB::raw('default_translation.merchant_language_id'), '=', 'languages.language_id');
                            })
                            ->where('promotions.promotion_id', $coupon_id)
                            ->first();

            // moved from generic activity number 38
            if (empty($skip) && OrbitInput::get('is_detail', 'n') === 'y'  ) {
                $coupon = Coupon::excludeDeleted()
                    ->where('promotion_id', $coupon_id)
                    ->first();

                $activityNotes = sprintf('Page viewed: Coupon location list');
                $activity->setUser($user)
                    ->setActivityName('view_coupon_location')
                    ->setActivityNameLong('View Coupon Location Page')
                    ->setObject($coupon)
                    ->setLocation($mall)
                    ->setModuleName('Coupon')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = $totalRec;
            if (is_object($couponName)) {
                $data->coupon_name = $couponName->coupon_name;
                $data->default_name = $couponName->default_name;
                $data->original_media_path = $couponName->original_media_path;
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
}
