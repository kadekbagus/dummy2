<?php namespace Orbit\Controller\API\v1\Pub\Store;
/**
 * An API controller for get all detail store in all mall, group by name.
 */
use BrandProductVariantOption;
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

            $image2 = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as cdn_url";
            if ($usingCdn) {
                $image2 = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as cdn_url";
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
                                'merchants.disable_ads',
                                'merchants.disable_ymal',
                                DB::raw('oms.country_id'),
                                DB::raw("CASE WHEN (
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
                                DB::raw("CASE WHEN (
                                                select cpt.custom_title
                                                from {$prefix}merchant_translations cpt
                                                where cpt.merchant_id = {$prefix}merchants.merchant_id
                                                    and cpt.merchant_language_id = {$this->quote($valid_language->language_id)}
                                            ) = ''
                                            THEN (
                                                select cpt.custom_title
                                                from {$prefix}merchant_translations cpt
                                                where cpt.merchant_id = {$prefix}merchants.merchant_id
                                                    and cpt.merchant_language_id = {$prefix}languages.language_id
                                            )
                                            ELSE (
                                                select cpt.custom_title
                                                from {$prefix}merchant_translations cpt
                                                where cpt.merchant_id = {$prefix}merchants.merchant_id
                                                    and cpt.merchant_language_id = {$this->quote($valid_language->language_id)}
                                            )
                                        END as custom_page_title
                                    "),
                                // new translation queries
                                DB::raw("CASE WHEN (
                                                select bst_description.meta_description
                                                from {$prefix}merchants m_description
                                                join {$prefix}base_store_translations bst_description on m_description.merchant_id = bst_description.base_store_id
                                                where m_description.merchant_id = {$this->quote($merchantId)}
                                                    and bst_description.language_id = {$this->quote($valid_language->language_id)}
                                            ) is null or (
                                                select bst_description.meta_description
                                                from {$prefix}merchants m_description
                                                join {$prefix}base_store_translations bst_description on m_description.merchant_id = bst_description.base_store_id
                                                where m_description.merchant_id = {$this->quote($merchantId)}
                                                    and bst_description.language_id = {$this->quote($valid_language->language_id)}
                                            ) = ''
                                            THEN (
                                                select bst_description.meta_description
                                                from {$prefix}merchants m_description
                                                join {$prefix}base_store_translations bst_description on m_description.merchant_id = bst_description.base_store_id
                                                where m_description.merchant_id = {$this->quote($merchantId)}
                                                    and bst_description.language_id = {$prefix}languages.language_id
                                            )
                                            ELSE (
                                                select bst_description.meta_description
                                                from {$prefix}merchants m_description
                                                join {$prefix}base_store_translations bst_description on m_description.merchant_id = bst_description.base_store_id
                                                where m_description.merchant_id = {$this->quote($merchantId)}
                                                    and bst_description.language_id = {$this->quote($valid_language->language_id)}
                                            )
                                        END as meta_description
                                    "),
                                DB::raw("CASE WHEN (
                                                select bmt_description.meta_description
                                                from {$prefix}merchants m_description
                                                join {$prefix}base_stores bs_description on m_description.merchant_id = bs_description.base_store_id
                                                join {$prefix}base_merchant_translations bmt_description on bs_description.base_merchant_id = bmt_description.base_merchant_id
                                                where m_description.merchant_id = {$this->quote($merchantId)}
                                                    and bmt_description.language_id = {$this->quote($valid_language->language_id)}
                                            ) is null or (
                                                select bmt_description.meta_description
                                                from {$prefix}merchants m_description
                                                join {$prefix}base_stores bs_description on m_description.merchant_id = bs_description.base_store_id
                                                join {$prefix}base_merchant_translations bmt_description on bs_description.base_merchant_id = bmt_description.base_merchant_id
                                                where m_description.merchant_id = {$this->quote($merchantId)}
                                                    and bmt_description.language_id = {$this->quote($valid_language->language_id)}
                                            ) = ''
                                            THEN (
                                                select bmt_description.meta_description
                                                from {$prefix}merchants m_description
                                                join {$prefix}base_stores bs_description on m_description.merchant_id = bs_description.base_store_id
                                                join {$prefix}base_merchant_translations bmt_description on bs_description.base_merchant_id = bmt_description.base_merchant_id
                                                where m_description.merchant_id = {$this->quote($merchantId)}
                                                    and bmt_description.language_id = {$prefix}languages.language_id
                                            )
                                            ELSE (
                                                select bmt_description.meta_description
                                                from {$prefix}merchants m_description
                                                join {$prefix}base_stores bs_description on m_description.merchant_id = bs_description.base_store_id
                                                join {$prefix}base_merchant_translations bmt_description on bs_description.base_merchant_id = bmt_description.base_merchant_id
                                                where m_description.merchant_id = {$this->quote($merchantId)}
                                                    and bmt_description.language_id = {$this->quote($valid_language->language_id)}
                                            )
                                        END as brand_meta_description
                                    "),
                                // end new translation queries
                                'merchants.url',
                                'merchants.facebook_url',
                                'merchants.instagram_url',
                                'merchants.twitter_url',
                                'merchants.youtube_url',
                                'merchants.line_url',
                                'merchants.other_photo_section_title',
                                'merchants.video_id_1',
                                'merchants.video_id_2',
                                'merchants.video_id_3',
                                'merchants.video_id_4',
                                'merchants.video_id_5',
                                'merchants.video_id_6',
                                'countries.name as country_name',
                                DB::raw('oms.city as city_name')
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
                    }, 'mediaImageOrig' => function ($q) use ($image, $image2) {
                        $q->select(
                                DB::raw("{$image}"),
                                DB::raw("{$image2}"),
                                'media.object_id',
                                'media.media_id',
                                'media.media_name_long',
                                'media.cdn_bucket_name',
                                'media.file_name',
                                'media.metadata'
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
                    }, 'mediaBanner' => function ($q) use ($image) {
                        $q->select(
                                DB::raw("{$image}"),
                                'media.object_id'
                            );
                    }
                    ])
                ->join(DB::raw("(select merchant_id, country_id, city_id, city, status, parent_id from {$prefix}merchants where object_type = 'mall') as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                ->join('countries', DB::raw('oms.country_id'), '=', 'countries.country_id')
                ->join('languages', 'languages.name', '=', 'merchants.mobile_default_language')
                ->where('merchants.merchant_id', $merchantId)
                ->where('merchants.status', 'active')
                ->whereRaw("oms.status = 'active'");

            $storeInfo = Tenant::select('merchants.name', 'merchants.status',DB::raw("oms.country"), DB::raw("oms.country_id"))
                            ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                            ->where(DB::raw('oms.status'), '=', 'active')
                            ->where('merchants.merchant_id', $merchantId)
                            ->first();

            // get photos for brand detail page
            $brandPhotos = Tenant::select('merchants.merchant_id')
                                    ->with(['baseStore' => function($q) {
                                        $q->with(['baseMerchant' => function($q2) {
                                            $q2->with('mediaPhotos');
                                            $q2->with('mediaOtherPhotos');
                                        }]);
                                    }])
                                    ->where('merchant_id', '=', $merchantId)
                                    ->first();

            $hasBrandProduct = BrandProductVariantOption::select('brand_products.status')
                ->join(
                    'brand_product_variants',
                    'brand_product_variant_options.brand_product_variant_id',
                    '=',
                    'brand_product_variants.brand_product_variant_id'
                )
                ->join(
                    'brand_products',
                    'brand_product_variants.brand_product_id',
                    '=',
                    'brand_products.brand_product_id'
                )
                ->where('option_id', $merchantId)
                ->where('brand_products.status', 'active')
                ->first() !== null;

            $photos = [];
            $otherPhotos = [];
            if ($brandPhotos) {
                if (isset($brandPhotos->baseStore) && isset($brandPhotos->baseStore->baseMerchant)) {
                    if (isset($brandPhotos->baseStore->baseMerchant->mediaPhotos)) {
                        $photos = $brandPhotos->baseStore->baseMerchant->mediaPhotos;
                    }
                    if (isset($brandPhotos->baseStore->baseMerchant->mediaOtherPhotos)) {
                        $otherPhotos = $brandPhotos->baseStore->baseMerchant->mediaOtherPhotos;
                    }
                }
            }

            // use cdn image when available
            $validPhotos = [];
            $validOtherPhotos = [];
            if (!empty($photos[0])) {
                foreach ($photos as $key => $value) {
                    if ($photos[$key]->cdn_url != '' && $usingCdn) {
                        $validImage = $photos[$key]->cdn_url;
                    } else {
                        $validImage = $urlPrefix.$photos[$key]->path;
                    }
                    $img = new stdClass();
                    $img->media_id = $photos[$key]->media_id;
                    $img->media_name_long = $photos[$key]->media_name_long;
                    $img->cdn_url = $validImage;
                    $img->cdn_bucket_name = $photos[$key]->cdn_bucket_name;
                    $img->file_name = $photos[$key]->file_name;
                    $img->metadata = $photos[$key]->metadata;
                    $validPhotos[] = $img;
                }
            }

            if (!empty($otherPhotos[0])) {
                foreach ($otherPhotos as $key => $value) {
                    if ($otherPhotos[$key]->cdn_url != '' && $usingCdn) {
                        $validImage = $otherPhotos[$key]->cdn_url;
                    } else {
                        $validImage = $urlPrefix.$otherPhotos[$key]->path;
                    }
                    $img = new stdClass();
                    $img->media_id = $otherPhotos[$key]->media_id;
                    $img->media_name_long = $otherPhotos[$key]->media_name_long;
                    $img->cdn_url = $validImage;
                    $img->cdn_bucket_name = $otherPhotos[$key]->cdn_bucket_name;
                    $img->file_name = $otherPhotos[$key]->file_name;
                    $img->metadata = $otherPhotos[$key]->metadata;
                    $validOtherPhotos[] = $img;
                }
            }

            if (! is_object($storeInfo)) {
                throw new OrbitCustomException('Unable to find store.', Tenant::NOT_FOUND_ERROR_CODE, NULL);
            }

            if (! empty($mallId)) {
                $mall = Mall::excludeDeleted()->where('merchant_id', '=', $mallId)->first();
            }

            $store->category_ids = $this->getBrandCategory($merchantId);

            if ($storeInfo->status != 'active') {
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

            $store->has_brand_product = $hasBrandProduct;

            foreach ($store->mediaImageOrig as $key => $value) {
                $validPhotos[] = $store->mediaImageOrig[$key];
            }

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
            $baseMerchants = BaseMerchant::where('name', $store->name)
                                        ->where('country_id', $store->country_id)
                                        ->first();

            $baseMerchantId = null;
            if (! empty($baseMerchants)) {
                $baseMerchantId = $baseMerchants->base_merchant_id;

                //output brand_id
                $store->brand_id = $baseMerchantId;

                // brand level
                if (empty($mallId)) {
                    $store->url = $baseMerchants->url;
                    $store->facebook_url = $baseMerchants->facebook_url;
                    $store->instagram_url = $baseMerchants->instagram_url;
                    $store->twitter_url = $baseMerchants->twitter_url;
                    $store->youtube_url = $baseMerchants->youtube_url;
                    $store->line_url = $baseMerchants->line_url;
                    $store->video_id_1 = $baseMerchants->video_id_1;
                    $store->video_id_2 = $baseMerchants->video_id_2;
                    $store->video_id_3 = $baseMerchants->video_id_3;
                    $store->video_id_4 = $baseMerchants->video_id_4;
                    $store->video_id_5 = $baseMerchants->video_id_5;
                    $store->video_id_6 = $baseMerchants->video_id_6;
                }
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

            $store->media_photos = $validPhotos;
            $store->media_other_photos = $validOtherPhotos;

            if (empty($mallId)) {
                // In brand detail page, use brand meta_desc if not empty.
                if (! empty($store->brand_meta_description)) {
                    $store->meta_description = $store->brand_meta_description;
                }
            }
            else {
                if (empty($store->meta_description)) {
                    $store->meta_description = $store->brand_meta_description;
                }
            }

            if (is_object($mall)) {
                // change store's mall_name to mall's name
                $store->mall_name = $mall->name;
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
            if ($this->response->code === 4040) {
                $httpCode = 404;
            } else {
                $httpCode = 500;
            }

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

    /**
     * Get Brand/store categories.
     *
     * @param  string $brandId [description]
     * @return [type]          [description]
     */
    private function getBrandCategory($brandId = '')
    {
        return Tenant::select('categories.category_id')
                       ->leftJoin('category_merchant', 'merchants.merchant_id', '=', 'category_merchant.merchant_id')
                       ->join('categories', 'category_merchant.category_id', '=', 'categories.category_id')
                       ->where('categories.merchant_id', 0)
                       ->where('categories.status', 'active')
                       ->where('merchants.merchant_id', $brandId)
                       ->groupBy('categories.category_id')
                       ->get()->lists('category_id');
    }


    private function getSelectDescriptionQuery($mallId = null, $prefix, $valid_language)
    {
        if (! empty($mallId)) {
            return DB::raw("CASE WHEN (
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
            ");
        }

        return DB::raw("CASE WHEN (
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
        ");
    }

    private function getSelectCustomTitleQuery($mallId = null, $prefix, $valid_language)
    {
        return DB::raw("CASE WHEN (
                    select cpt.custom_title
                    from {$prefix}merchant_translations cpt
                    where cpt.merchant_id = {$prefix}merchants.merchant_id
                        and cpt.merchant_language_id = {$this->quote($valid_language->language_id)}
                ) = ''
                THEN (
                    select cpt.custom_title
                    from {$prefix}merchant_translations cpt
                    where cpt.merchant_id = {$prefix}merchants.merchant_id
                        and cpt.merchant_language_id = {$prefix}languages.language_id
                )
                ELSE (
                    select cpt.custom_title
                    from {$prefix}merchant_translations cpt
                    where cpt.merchant_id = {$prefix}merchants.merchant_id
                        and cpt.merchant_language_id = {$this->quote($valid_language->language_id)}
                )
            END as custom_page_title
        ");
    }

}
