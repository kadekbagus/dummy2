<?php namespace Orbit\Controller\API\v1\Pub\Article;

use Config;
use Exception;
use Article;
use ArticleSearch;
use Validator;
use Activity;
use Mall;
use Tenant;
use News;
use Coupon;
use stdClass;
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Helper\Util\PaginationNumber;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Carbon\Carbon as Carbon;

use Orbit\Controller\API\v1\Article\ArticleHelper;

/**
 * Class that handle get article list.
 */
class ArticleListAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;

    protected $withoutScore = FALSE;

    /**
     * Enable / disable scroll function on ES
     */
    protected $useScroll = FALSE;

    /**
     * Scroll duration when $useScroll is TRUE
     */
    protected $scrollDuration = '20s';

    /**
     * Searcher instance.
     */
    protected $searcher = null;

    function __construct()
    {
        parent::__construct();
        $this->searcher = new ArticleSearch();
    }

    /**
     * GET - get active article in all mall, and also provide for searching
     *
     * @author Budi <budi@dominopos.com>
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
    public function getSearchArticle()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $keyword = null;
        $user = null;
        $mall = null;
        $cacheKey = [];
        $serializedCacheKey = [];

        // Cache result of all possible calls to backend storage
        $cacheConfig = Config::get('orbit.cache.context');
        $cacheContext = 'article-list';
        $recordCache = SimpleCache::create($cacheConfig, $cacheContext);
        $totalRecordCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-total-rec');
        $keywordSearchCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-keyword-search');

        try {
            $user = $this->getUser();
            $sortBy = OrbitInput::get('sortby', 'created_date');
            $sortMode = OrbitInput::get('sortmode','desc');
            $language = OrbitInput::get('language', 'id');
            $countryFilter = OrbitInput::get('country', null);
            $cityFilters = OrbitInput::get('cities', []);
            $objectType = OrbitInput::get('object_type', null);
            $objectId = OrbitInput::get('object_id', null);
            // $mallId = OrbitInput::get('mall_id', null);
            $categoryIds = OrbitInput::get('category_id', []);
            // $list_type = OrbitInput::get('list_type', 'featured');
            $from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $no_total_records = OrbitInput::get('no_total_records', null);
            $take = PaginationNumber::parseTakeFromGet('news');
            $skip = PaginationNumber::parseSkipFromGet();
            $withCache = TRUE;
            $viewType = OrbitInput::get('view_type', 'grid');
            $excludedItems = OrbitInput::get('except', []);

            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();

            $validator = Validator::make(
                array(
                    'language' => $language,
                    'sortby'   => $sortBy,
                    // 'list_type'   => $list_type,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,created_date,updated_date,relevance',
                    'list_type'   => 'in:featured,preferred',
                ),
                array(
                )
            );

            // Pass all possible parameters to be used as cache key.
            // Make sure there is no missing one.
            $cacheKey = [
                'sortBy' => $sortBy,
                'sortMode' => $sortMode,
                'language' => $language,
                'object_type' => $objectType,
                'object_id' => $objectId,
                // 'mall_id' => $mallId,
                // 'list_type' => $list_type,
                'from_mall_ci' => $from_mall_ci,
                'category_id' => $categoryIds,
                'excludedItems' => $excludedItems,
                'no_total_record' => $no_total_records,
                'take' => $take, 'skip' => $skip,
                'country' => $countryFilter,
                'cities' => $cityFilters,
            ];

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $couponHelper->getValidLanguage();

            //Get now time, time must be 2017-01-09T15:30:00Z
            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateNow = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $withScore = false;

            $this->searcher->setPaginationParams(['from' => $skip, 'size' => $take]);

            $searchKeyword = false;
            $searchCategories = false;
            $searchLinkedObjects = false;

            // Only search active promotions..
            // $this->searcher->isActive();
            $this->searcher->isActive(compact('dateTimeEs'));

            // Only filter based on specific country.
            if (! empty($countryFilter) && $countryFilter !== '0') {
                $this->searcher->filterByCountry($countryFilter);
            }

            if (! empty($cityFilters) && $cityFilters !== '0') {
                $this->searcher->filterByCities($cityFilters);
            }

            // Exclude an article
            $this->searcher->filterExclude($excludedItems);

            // Filter by linked object like malls, brands, etc...
            // Linked object should have higher priority than category.
            if (! empty($objectType) && ! empty($objectId)) {
                $searchLinkedObjects = true;
            }

            // Filter by given keyword...
            // Keyword can be a Mall Name (for mall detail page)
            $keyword = OrbitInput::get('keyword', null);
            $forbiddenCharacter = array('>', '<', '(', ')', '{', '}', '[', ']', '^', '"', '~', '/');
            $keyword = str_replace($forbiddenCharacter, '', $keyword);
            if (! empty($keyword)) {
                $cacheKey['keyword'] = $keyword;
                $searchKeyword = true;

                // Force to sort by relevance if keyword is set.
                $sortBy = 'relevance';
            }

            // Get object categories.
            // Useful for case like related article to a campaign/object.
            // We will query the object to root of their store/mall and get the category.
            // Merge with the requested categories (if any).
            $categoryIds = array_merge($categoryIds, $this->getObjectCategories());

            if (! empty($categoryIds)) {
                $searchCategories = true;
            }

            // Build search filter
            if ($searchLinkedObjects) {
                $this->searcher->filterByLinkedObject($objectType, $objectId, 'should');

                if ($searchCategories) {
                    $this->searcher->filterByCategories($categoryIds, 'should');
                }

                if ($searchKeyword) {
                    $this->searcher->filterByKeyword($keyword, 'should');
                }

                $this->searcher->minimumShouldMatch(1);
            }
            else {
                if ($searchCategories) {
                    $this->searcher->filterByCategories($categoryIds, 'must');
                }

                if ($searchKeyword) {
                    $this->searcher->filterByKeyword($keyword, 'must');
                }
            }

            switch ($sortBy) {
                case 'relevance':
                    // For related article, landing page must pass sortBy relevance.
                    $this->searcher->sortByRelevance();
                    $this->searcher->sortByPublishingDate();
                    break;
                default:
                    $this->searcher->sortByPublishingDate($sortMode);
                    break;
            }

            if ($this->useScroll) {
                $this->searcher->setParams([
                    'search_type' => 'scan',
                    'scroll' => $this->scrollDuration,
                ]);
                $this->searcher->removeParamItem('body.aggs');

                return $this->searcher->getResult();
            }

            if ($withCache) {
                $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);
                $response = $recordCache->get($serializedCacheKey, function() {
                    return $this->searcher->getResult();
                });
                $recordCache->put($serializedCacheKey, $response);
            } else {
                $response = $this->searcher->getResult();
            }

            $records = $response['hits'];

            $listOfRec = array();
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGeneratorWithCloudfront::create(['cdn' => $cdnConfig], 'cdn');

            foreach ($records['hits'] as $record) {
                $data = array();
                $default_lang = '';
                $pageView = 0;
                foreach ($record['_source'] as $key => $value) {
                    $data[$key] = $value;

                    // Get full image url
                    if ($key === 'media') {
                        foreach($value as $index => $media) {
                            $localPath = isset($media['image_url']) ? $media['image_url'] : '';
                            $cdnPath = isset($media['image_cdn_url']) ? $media['image_cdn_url'] : '';

                            $data['media'][$index]['image_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
                        }
                    }
                }

                $data['page_view'] = $pageView;
                $data['score'] = $record['_score'];
                $listOfRec[] = $data;
            }

            // frontend need the mall name
            $mall = null;
            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = $records['total'];
            if (is_object($mall)) {
                $data->mall_name = $mall->name;
                $data->mall_city = $mall->city;
            }
            $data->records = $listOfRec;

            // save activity when accessing listing
            // omit save activity if accessed from mall ci campaign list 'from_mall_ci' !== 'y'
            // moved from generic activity number 32
            if (OrbitInput::get('from_homepage', '') !== 'y') {
                if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                    if (is_object($mall)) {
                        $activityNotes = sprintf('Page viewed: View mall article list');
                        $activity->setUser($user)
                            ->setActivityName('view_mall_article_list')
                            ->setActivityNameLong('View mall article list')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Article')
                            ->setNotes($activityNotes)
                            ->setObjectDisplayName($viewType)
                            ->responseOK()
                            ->save();
                    } else {
                        $activityNotes = sprintf('Page viewed: Article list');
                        $activity->setUser($user)
                            ->setActivityName('view_articles_main_page')
                            ->setActivityNameLong('View Articles Main Page')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Article')
                            ->setNotes($activityNotes)
                            ->setObjectDisplayName($viewType)
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

        return $this->render($httpCode);
    }

    /**
     * Force $withScore value to FALSE, ignoring previously set value
     * @param $bool boolean
     */
    public function setWithOutScore()
    {
        $this->withoutScore = TRUE;

        return $this;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    /**
     * Force $useScroll value to TRUE, ignoring previously set value
     * @param $bool boolean
     */
    public function setUseScroll()
    {
        $this->useScroll = TRUE;

        return $this;
    }

    /**
     * Set $scrollDuration, use less when $useScroll is FALSE
     * @param $scrollDuration int
     */
    public function setScrollDuration($scrollDuration=20)
    {
        $this->scrollDuration = $scrollDuration . 's';

        return $this;
    }

    /**
     * Get Searcher
     */
    public function getSearcher()
    {
        return $this->searcher->getActiveClient();
    }

    /**
     * Get object categories.
     *
     * @return [type] [description]
     */
    private function getObjectCategories()
    {
        $objectType = OrbitInput::get('object_type', '');
        $objectId = OrbitInput::get('object_id', '');

        switch ($objectType) {
            case 'event':
            case 'promotion':
                return $this->getNewsCategory($objectId);
                break;

            case 'brand':
            case 'store':
                return $this->getBrandCategory($objectId);
                break;

            case 'coupon':
                return $this->getCouponCategory($objectId);
                break;

            default:
                return [];
                break;
        }
    }

    /**
     * Get news/promotion categories.
     *
     * @param  string $newsId [description]
     * @return [type]         [description]
     */
    private function getNewsCategory($newsId = '')
    {
        return News::select('categories.category_id')
                     ->leftJoin('news_merchant', 'news.news_id', '=', 'news_merchant.news_id')
                     ->leftJoin('category_merchant', 'news_merchant.merchant_id', '=', 'category_merchant.merchant_id')
                     ->join('categories', 'category_merchant.category_id', '=', 'categories.category_id')
                     ->where('categories.merchant_id', 0)
                     ->where('categories.status', 'active')
                     ->where('news.news_id', $newsId)
                     ->groupBy('categories.category_id')
                     ->get()->lists('category_id');
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

    /**
     * Get coupon categories.
     *
     * @param  string $couponId [description]
     * @return [type]           [description]
     */
    private function getCouponCategory($couponId = '')
    {
        return Coupon::select('category_merchant.category_id')
                       ->leftJoin('promotion_retailer', 'promotions.promotion_id', '=', 'promotion_retailer.promotion_id')
                       ->leftJoin('merchants', 'promotion_retailer.retailer_id', '=', 'merchants.merchant_id')
                       ->leftJoin('category_merchant', 'merchants.merchant_id', '=', 'category_merchant.merchant_id')
                       ->join('categories', 'category_merchant.category_id', '=', 'categories.category_id')
                       ->where('categories.merchant_id', 0)
                       ->where('categories.status', 'active')
                       ->where('promotions.promotion_id', $couponId)
                       ->groupBy('categories.category_id')
                       ->get()->lists('category_id');
    }
}
