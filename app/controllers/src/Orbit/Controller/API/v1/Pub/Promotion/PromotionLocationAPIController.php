<?php namespace Orbit\Controller\API\v1\Pub\Promotion;

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
use Orbit\Controller\API\v1\Pub\Promotion\PromotionHelper;
use Mall;
use Orbit\Helper\Util\SimpleCache;

class PromotionLocationAPIController extends PubControllerAPI
{

    /**
     * GET - Get list location (stores and malls) of the promotion
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string promotion_id
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

    public function getPromotionLocations()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;
        $mall = null;

        $cacheKey = [];
        $serializedCacheKey = [];

        // Cache result of all possible calls to backend storage
        $cacheConfig = Config::get('orbit.cache.context');
        $cacheContext = 'location-promotion-list';

        $recordCache = SimpleCache::create($cacheConfig, $cacheContext);
        $totalRecordCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-total-rec');

        try{
            $user = $this->getUser();

            $promotion_id = OrbitInput::get('promotion_id', null);
            $mall_id = OrbitInput::get('mall_id', null);
            $is_detail = OrbitInput::get('is_detail', 'n');
            $is_mall = OrbitInput::get('is_mall', 'n');
            $location = (array) OrbitInput::get('location', []);
            $country = OrbitInput::get('country');
            $cities = (array) OrbitInput::get('cities', []);
            $distance = Config::get('orbit.geo_location.distance', 10);
            $ul = OrbitInput::get('ul', null);
            $language = OrbitInput::get('language', 'id');
            $take = PaginationNumber::parseTakeFromGet('news');
            $skip = PaginationNumber::parseSkipFromGet();
            $withCache = TRUE;

            // need to handle request for grouping by name_orig and order by name_orig and city
            $groupBy = OrbitInput::get('group_by');

            $promotionHelper = PromotionHelper::create();
            $promotionHelper->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'promotion_id' => $promotion_id,
                    'language' => $language,
                    'group_by' => $groupBy,
                ),
                array(
                    'promotion_id' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                    'group_by' => 'in:name_orig',
                ),
                array(
                    'required' => 'Promotion ID is required',
                )
            );

            // Pass all possible parameters to be used as cache key.
            // Make sure there is no missing one.
            $cacheKey = [
                'promotion_id' => $promotion_id,
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
                'group_by' => $groupBy,
            ];


            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $promotionHelper->getValidLanguage();

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

            $promotionLocation = NewsMerchant::select(
                                        DB::raw("{$prefix}merchants.merchant_id as merchant_id"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.parent_id ELSE {$prefix}merchants.merchant_id END as mall_id"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END as city"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN CONCAT({$prefix}merchants.name, ' at ', oms.name) ELSE {$prefix}merchants.name END as name"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.name ELSE {$prefix}merchants.name END as mall_name"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.address_line1 ELSE {$prefix}merchants.address_line1 END as address"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.floor ELSE '' END as floor"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.unit ELSE '' END as unit"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.operating_hours ELSE {$prefix}merchants.operating_hours END as operating_hours"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.is_subscribed ELSE {$prefix}merchants.is_subscribed END as is_subscribed"),
                                        DB::raw("{$prefix}merchants.object_type as location_type"),
                                        DB::raw("{$mallLogo}"),
                                        DB::raw("{$locationLogo}"),
                                        DB::raw("{$mallMap}"),
                                        DB::raw("{$prefix}merchants.phone as phone"),
                                        DB::raw("{$prefix}merchants.name as name_orig"),
                                        DB::raw("x(position) as latitude"),
                                        DB::raw("y(position) as longitude")
                                    )
                                    ->leftJoin('news', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->leftJoin('merchant_geofences', 'merchant_geofences.merchant_id', '=', DB::raw("IF({$prefix}merchants.object_type = 'tenant', {$prefix}merchants.parent_id, {$prefix}merchants.merchant_id)"))
                                    // Map
                                    ->leftJoin(DB::raw("{$prefix}media as map"), function($q) use ($prefix){
                                        $q->on(DB::raw('map.object_id'), '=', "merchants.merchant_id")
                                          ->on(DB::raw('map.media_name_long'), 'IN', DB::raw("('mall_map_orig', 'retailer_map_orig')"));
                                    })
                                    // Mall Logo
                                    ->leftJoin(DB::raw("{$prefix}media as img"), function($q) use ($prefix){
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
                                    ->where('news_merchant.news_id', '=', $promotion_id)
                                    ->where('merchants.status', '=', 'active');

            // filter news by mall id
            OrbitInput::get('mall_id', function($mallid) use ($promotionLocation, &$group_by) {
                $promotionLocation->where(function($q) use ($mallid){
                                    $q->where('merchants.parent_id', '=', $mallid)
                                      ->orWhere('merchants.merchant_id', '=', $mallid);
                                });
            });

            // Get user location
            $position = isset($ul)?explode("|", $ul):null;
            $lon = isset($position[0])?$position[0]:null;
            $lat = isset($position[1])?$position[1]:null;

            OrbitInput::get('country', function($country) use ($promotionLocation, $prefix) {
                    $promotionLocation->whereIn(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.country ELSE {$prefix}merchants.country END)"), $country);
            });

            // Filter by location
            if (! empty($location)) {
                if ($location == 'mylocation' && ! empty($lon) && ! empty($lat)) {
                    $withCache = FALSE;
                    $promotionLocation->addSelect(DB::raw("6371 * acos( cos( radians({$lat}) ) * cos( radians( x({$prefix}merchant_geofences.position) ) ) * cos( radians( y({$prefix}merchant_geofences.position) ) - radians({$lon}) ) + sin( radians({$lat}) ) * sin( radians( x({$prefix}merchant_geofences.position) ) ) ) AS distance"))
                                        ->havingRaw("distance <= {$distance}");
                } else {
                    if (! in_array('0', $location)) {
                        $promotionLocation->whereIn(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END)"), $location);
                    }
                }
            } else {
                if ($is_mall !== 'y' && ! empty($cities)) { // handle all location from mall level
                    // filter by cities
                    if (! in_array('0', $cities)) {
                        $promotionLocation->whereIn(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END)"), $cities);
                    }
                }
            }

            // Order data by nearby or city alphabetical
            if ($location == 'mylocation' && ! empty($lon) && ! empty($lat)) {
                $withCache = FALSE;
                $promotionLocation->orderBy('distance', 'asc');
            } else {
                if (! empty($groupBy)) {
                    $promotionLocation->orderBy('name_orig', 'asc')
                        ->orderBy('city', 'asc');
                } else {
                    $promotionLocation->orderBy('name', 'asc');
                }
            }

            if (! empty($groupBy)) {
                $promotionLocation->groupBy('name_orig');
            } else {
                $promotionLocation->groupBy('merchants.merchant_id');
            }

            $_promotionLocation = clone($promotionLocation);

            $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);

            $recordCounter = RecordCounter::create($_promotionLocation);

            // Try to get the result from cache
            if ($withCache) {
                $totalRec = $totalRecordCache->get($serializedCacheKey, function() use ($recordCounter) {
                    return $recordCounter->count();
                });
                $totalRecordCache->put($serializedCacheKey, $totalRec);
            } else {
                $totalRec = $recordCounter->count();
            }

            $promotionLocation->take($take);
            $promotionLocation->skip($skip);

            // Try to get the result from cache
            if ($withCache) {
                $listOfRec = $recordCache->get($serializedCacheKey, function() use ($promotionLocation) {
                    return $promotionLocation->get();
                });
                $recordCache->put($serializedCacheKey, $listOfRec);
            } else {
                $listOfRec = $promotionLocation->get();
            }

            $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
            if ($usingCdn) {
                $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
            }

            $promotionName = News::select(DB::Raw("
                                CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as promotion_name,
                                CASE WHEN (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'news_translation_image_orig'
                                    AND m.object_id = {$prefix}news_translations.news_translation_id) is null
                                THEN
                                    (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'news_translation_image_orig'
                                    AND m.object_id = default_translation.news_translation_id)
                                ELSE
                                    (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'news_translation_image_orig'
                                    AND m.object_id = {$prefix}news_translations.news_translation_id)
                                END AS original_media_path
                            "))
                        ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                        ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                        ->leftJoin('news_translations', function ($q) use ($valid_language) {
                            $q->on('news_translations.news_id', '=', 'news.news_id')
                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })
                        ->leftJoin('news_translations as default_translation', function ($q) use ($prefix){
                            $q->on(DB::raw("default_translation.news_id"), '=', 'news.news_id')
                              ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                        })
                        ->where('news.news_id', $promotion_id)
                        ->where('news.object_type', '=', 'promotion')
                        ->first();

            // moved from generic activity number 36
            if (empty($skip) && OrbitInput::get('is_detail', 'n') === 'y'  ) {
                $promotion = News::excludeDeleted()
                    ->where('news_id', $promotion_id)
                    ->first();

                $activityNotes = sprintf('Page viewed: Promotion location list');
                $activity->setUser($user)
                    ->setActivityName('view_promotion_location')
                    ->setActivityNameLong('View Promotion Location Page')
                    ->setObject($promotion)
                    ->setLocation($mall)
                    ->setModuleName('Promotion')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = $totalRec;
            if (is_object($promotionName)) {
                $data->promotion_name = $promotionName->promotion_name;
                $data->original_media_path = $promotionName->original_media_path;
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