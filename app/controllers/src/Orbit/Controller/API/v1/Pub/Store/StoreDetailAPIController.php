<?php namespace Orbit\Controller\API\v1\Pub\Store;
/**
 * An API controller for get all detail store in all mall, group by name.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use Mall;
use BaseMerchant;
use BaseStore;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Language;
use Activity;
use Lang;
use Tenant;
use \Orbit\Helper\Exception\OrbitCustomException;
use TotalObjectPageView;
use Redis;
use Orbit\Helper\Util\FollowStatusChecker;

class StoreDetailAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $store = NULL;
    protected $withoutScore = FALSE;

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
            $user = $this->getUser();

            // Call validation from store helper
            $storeHelper = StoreHelper::create();
            $storeHelper->registerCustomValidation();

            $merchantId = OrbitInput::get('merchant_id');
            $language = OrbitInput::get('language', 'id');
            $mallId = OrbitInput::get('mall_id', null);
            $cityFilters = OrbitInput::get('cities', []);
            $cityFilters = (array) $cityFilters;

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'merchantid' => $merchantId,
                    'language' => $language,
                ),
                array(
                    'merchantid' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                ),
                array(
                    'required' => 'Merchant id is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();
            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as path";
            if ($usingCdn) {
                $image = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as path";
            }

            $location = $mallId;
            if (empty($location)) {
                $location = 0;
            }

            $store = Tenant::select(
                                'merchants.merchant_id',
                                'merchants.name',
                                'merchants.name as mall_name',
                                'merchants.description as mall_description',
                                DB::raw('oms.country_id'),
                                DB::Raw("CASE WHEN (
                                                select mt.description
                                                from {$prefix}merchant_translations mt
                                                where mt.merchant_id = {$prefix}merchants.merchant_id
                                                    and mt.merchant_language_id = {$this->quote($valid_language->language_id)}
                                            ) = ''
                                            THEN (
                                                select mt.description
                                                from {$prefix}merchant_translations mt
                                                where mt.merchant_id = {$prefix}merchants.merchant_id
                                                    and mt.merchant_language_id = {$prefix}languages.language_id
                                            )
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
                    }, 'mediaLogo' => function ($q) use ($image) {
                        $q->select(
                                DB::raw("{$image}"),
                                'media.object_id'
                            );
                    }, 'mediaImageOrig' => function ($q) use ($image) {
                        $q->select(
                                DB::raw("{$image}"),
                                'media.object_id'
                            );
                    }, 'mediaImageCroppedDefault' => function ($q) use ($image) {
                        $q->select(
                                DB::raw("{$image}"),
                                'media.object_id'
                            );
                    }, 'mediaMapOrig' => function ($q) use ($image) {
                        $q->select(
                                DB::raw("{$image}"),
                                'media.object_id'
                            );
                    }, 'keywords' => function ($q) {
                        $q->addSelect('keyword', 'object_id');
                        $q->groupBy('keyword');
                    }, 'product_tags' => function ($q) {
                        $q->addSelect('product_tag', 'object_id');
                        $q->groupBy('product_tag');
                    }
                    ])
                ->join(DB::raw("(select merchant_id, country_id, status, parent_id from {$prefix}merchants where object_type = 'mall') as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                ->join('languages', 'languages.name', '=', 'merchants.mobile_default_language')
                ->where('merchants.merchant_id', $merchantId)
                ->where('merchants.status', 'active')
                ->whereRaw("oms.status = 'active'");

            $storeInfo = Tenant::select('merchants.name', 'merchants.status',DB::raw("oms.country"), DB::raw("oms.country_id"))
                            ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                            ->where(DB::raw('oms.status'), '=', 'active')
                            ->where('merchants.merchant_id', $merchantId)
                            ->first();

            if (! is_object($storeInfo)) {
                throw new OrbitCustomException('Unable to find store.', Tenant::NOT_FOUND_ERROR_CODE, NULL);
            }

            if (! empty($mallId)) {
                $mall = Mall::excludeDeleted()->where('merchant_id', '=', $mallId)->first();
            }

            if (! empty($storeInfo) && $storeInfo->status != 'active') {
                $mallName = 'gtm';
                if (! empty($mall)) {
                    $mallName = $mall->name;
                }

                $customData = new \stdClass;
                $customData->type = 'store';
                $customData->location = $location;
                $customData->mall_name = $mallName;
                throw new OrbitCustomException('Store is inactive', Tenant::INACTIVE_ERROR_CODE, $customData);
            }

            $store = $store->orderBy('merchants.created_at', 'asc')
                ->first();

            // Config page_views
            $configPageViewSource = Config::get('orbit.page_view.source', FALSE);
            $configPageViewRedisDb = Config::get('orbit.page_view.redis.connection', FALSE);
            $totalPageViews = 0;

            $storeName = null;
            $storeCountryId = null;
            if (! empty($store)) {
                $storeName = $store->name;
                $storeCountryId = $store->country_id;
            }

            // Get base_merchant_id
            $baseMerchants = BaseMerchant::select('base_merchant_id')
                                        ->where('name', $store->name)
                                        ->where('country_id', $store->country_id)
                                        ->first();

            $baseMerchantId = null;
            if (! empty($baseMerchants)) {
                $baseMerchantId =$baseMerchants->base_merchant_id;
            }

            // Get total page views, depend of config what DB used
            if ($configPageViewSource === 'redis') {
                $keyRedis = 'tenant||' . $baseMerchantId . '||' . $location;
                $redis = Redis::connection($configPageViewRedisDb);
                $totalPageViewRedis = $redis->get($keyRedis);

                if (! empty($totalPageViewRedis)) {
                    $totalPageViews = $totalPageViewRedis;
                } else {
                    $totalObjectPageView = TotalObjectPageView::where('object_type', 'tenant')
                                                                 ->where('object_id', $baseMerchantId)
                                                                 ->where('location_id', $location)
                                                                 ->first();

                    if (! empty($totalObjectPageView->total_view)) {
                        $totalPageViews = $totalObjectPageView->total_view;
                    }
                }
            } else {
                $totalObjectPageView = TotalObjectPageView::where('object_type', 'tenant')
                                                             ->where('object_id', $baseMerchantId)
                                                             ->where('location_id', $location)
                                                             ->first();

                if (! empty($totalObjectPageView->total_view)) {
                    $totalPageViews = $totalObjectPageView->total_view;
                }
            }
            $store->total_view = $totalPageViews;

            // Get status followed
            $role = $user->role->role_name;
            $objectFollow = [];
            $followed = false;
            if (strtolower($role) === 'consumer') {
                $objectFollow = $this->getUserFollow($user, $baseMerchantId, $location, $cityFilters); // return

                if (! empty($objectFollow)) {
                    $followed = true;
                }
            }
            $store->follow_status = $followed;

            // ---- START RATING ----
            $storeIds = [];
            $storeIdList = Tenant::select('merchants.merchant_id')
                            ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                            ->where('merchants.status', '=', 'active')
                            ->where(DB::raw('oms.status'), '=', 'active')
                            ->where('merchants.name', $storeInfo->name)
                            ->where(DB::raw("oms.country"), $storeInfo->country)
                            ->get();

            foreach ($storeIdList as $storeId) {
                $storeIds[] = $storeId->merchant_id;
            }

            $reviewCounter = \Orbit\Helper\MongoDB\Review\ReviewCounter::create(Config::get('database.mongodb'))
                ->setObjectId($storeIds)
                ->setObjectType('store')
                ->setMall($mall)
                ->request();

            $store->rating_average = $reviewCounter->getAverage();
            $store->review_counter = $reviewCounter->getCounter();
            // ---- END OF RATING ----

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
        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getCustomData();
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

    // check user follow
    public function getUserFollow($user, $merchantId, $mallId, $city=array())
    {
        $follow = FollowStatusChecker::create()
                                    ->setUserId($user->user_id)
                                    ->setObjectType('store');
        if (! empty($merchantId)) {
            $follow = $follow->setObjectId($merchantId);
        }

        if (! empty($mallId)) {
            $follow = $follow->setMallId($mallId);
        }

        if (! empty($city)) {
            if (! is_array($city)) {
                $city = (array) $city;
            }
            $follow = $follow->setCity($city);
        }

        $follow = $follow->getFollowStatus();

        return $follow;
    }

}