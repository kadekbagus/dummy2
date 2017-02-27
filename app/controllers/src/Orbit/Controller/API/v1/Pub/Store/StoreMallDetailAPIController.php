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

        try {
            $user = $this->getUser();
            $mallId = OrbitInput::get('mall_id', null);
            $merchantId = OrbitInput::get('merchant_id');

            $ul = OrbitInput::get('ul', null);
            $take = PaginationNumber::parseTakeFromGet('retailer');
            $skip = PaginationNumber::parseSkipFromGet();

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchantId,
                ),
                array(
                    'merchant_id' => 'required',
                ),
                array(
                    'required' => 'Merchant id is required',
                )
            );

            // Pass all possible parameters to be used as cache key.
            // Make sure there is no missing one.
            $cacheKey = [
                'mall_id' => $mallId,
                'merchant_id' => $merchantId,
                'take' => $take,
                'skip' => $skip,
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

            // Get store name base in merchant_id
            $store = Tenant::select('merchant_id', 'name', 'country_id')->where('merchant_id', $merchantId)->active()->first();
            if (! empty($store)) {
                $storename = $store->name;
                $countryId = $store->country_id;
            }

            $mall = Tenant::select(
                                    'merchants.merchant_id',
                                    DB::raw("mall.merchant_id as mall_id"),
                                    DB::raw("mall.city"),
                                    'merchants.name',
                                    DB::raw("mall.name as mall_name"),
                                    DB::raw("mall.address_line1 as address"),
                                    'merchants.floor',
                                    'merchants.unit',
                                    DB::raw("mall.operating_hours"),
                                    DB::raw("mall.is_subscribed"),
                                    DB::raw("mall.object_type as location_type"),
                                    DB::raw("{$mallLogo}"),
                                    DB::raw("{$mallMap}"),
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
                              ->where('merchants.country_id', $countryId)
                              ->where(DB::raw("mall.status"), 'active');

            if (! empty($mallId)) {
                $mall->where(DB::raw("mall.merchant_id"), '=', $mallId)->first();
            }

            // Order data city alphabetical
            $mall->orderBy('city', 'asc');
            $mall->orderBy('merchants.name', 'asc');

            $mall = $mall->groupBy('merchants.merchant_id');

            $_mall = clone $mall;
            $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);
            $recordCounter = RecordCounter::create($_mall);

            // Try to get the result from cache
            $totalRec = $totalRecordCache->get($serializedCacheKey, function() use ($recordCounter) {
                return $recordCounter->count();
            });

            // Put the result in cache if it is applicable
            $totalRecordCache->put($serializedCacheKey, $totalRec);

            $mall->take($take);
            $mall->skip($skip);

            // Try to get the result from cache
            $listOfRec = $recordCache->get($serializedCacheKey, function() use ($mall) {
                return $mall->get();
            });
            $recordCache->put($serializedCacheKey, $listOfRec);

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

            $this->response->data = new stdClass();
            $this->response->data->total_records = $totalRec;
            $this->response->data->returned_records = count($listOfRec);
            $this->response->data->records = $listOfRec;
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
