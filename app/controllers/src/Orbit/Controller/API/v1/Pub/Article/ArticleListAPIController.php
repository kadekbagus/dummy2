<?php namespace Orbit\Controller\API\v1\Pub\Article;
/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for article list and search in landing page
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
use Redis;

use Article;
use Language;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;

use Mall;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\Util\CdnUrlGenerator;
use Elasticsearch\ClientBuilder;
use Carbon\Carbon as Carbon;
use stdClass;
use Country;
use UserSponsor;

use Orbit\Controller\API\v1\Article\ArticleHelper;


class ArticleListAPIController extends PubControllerAPI
{
    protected $withoutScore = FALSE;

    /**
     * GET - get active article, and also provide for searching
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
            $host = Config::get('orbit.elasticsearch');
            $sort_by = OrbitInput::get('sortby', 'created_date');
            $sort_mode = OrbitInput::get('sortmode','desc');
            $countryFilter = OrbitInput::get('country', null);


            $ul = OrbitInput::get('ul', null);

            $mallId = OrbitInput::get('mall_id', null);
            $category_id = OrbitInput::get('category_id');
            $list_type = OrbitInput::get('list_type', 'featured');
            $from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $no_total_records = OrbitInput::get('no_total_records', null);
            $take = PaginationNumber::parseTakeFromGet('news');
            $skip = PaginationNumber::parseSkipFromGet();
            $withCache = TRUE;
            $viewType = OrbitInput::get('view_type', 'grid');

            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $articleHelper = ArticleHelper::create();
            $articleHelper->articleCustomValidator();

            $validator = Validator::make(
                array(
                    'sortby'   => $sort_by,
                ),
                array(
                    'sortby'   => 'in:name,created_date,updated_date,rating',
                )
            );

            // Pass all possible parameters to be used as cache key.
            // Make sure there is no missing one.
            $cacheKey = [
                'sort_by' => $sort_by,
                'sort_mode' => $sort_mode,
                'mall_id' => $mallId,
                'from_mall_ci' => $from_mall_ci,
                'category_id' => $category_id,
                'no_total_record' => $no_total_records,
                'take' => $take,
                'skip' => $skip,
                'country' => $countryFilter
            ];

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            $withScore = false;
            $esTake = $take;

            //Get now time, time must be 2017-01-09T15:30:00Z
            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $jsonQuery = array(
                'from' => $skip,
                'size' => $esTake,
                'fields' => array(
                    "_source"
                ) ,
                'query' => array(
                    'bool' => array(
                        'filter' => array(
                            array(
                                'query' => array(
                                    'match' => array(
                                        'status' => 'active'
                                    )
                                )
                            ),
                            array(
                                'range' => array(
                                    'published_at' => array(
                                        'lte' => $dateTimeEs
                                    )
                                )
                            )
                        )
                    )
                )
            );

            $withKeywordSearch = false;
            $filterKeyword = [];
            OrbitInput::get('keyword', function($keyword) use (&$jsonQuery, &$searchFlag, &$withScore, &$cacheKey, &$filterKeyword, &$withKeywordSearch)
            {
                $cacheKey['keyword'] = $keyword;

                if ($keyword != '') {
                    $searchFlag = $searchFlag || TRUE;
                    $withKeywordSearch = true;
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.articles.keyword', '');

                    $priority['title'] = Config::get('orbit.elasticsearch.priority.articles.title', '^6');
                    $priority['body'] = Config::get('orbit.elasticsearch.priority.articles.body', '^5');

                    $filterKeyword['bool']['should'][] = array(
                                                                'query' => array(
                                                                    'multi_match' => array(
                                                                        'query' => $keyword,
                                                                        'fields' => array(
                                                                            'title' . $priority['title'],
                                                                            'body' . $priority['body']
                                                                        )
                                                                    )
                                                                )
                                                            );

                    if ($shouldMatch != '') {
                        $filterKeyword['bool']['minimum_should_match'] = $shouldMatch;
                    }

                    $jsonQuery['query']['bool']['filter'][] = $filterKeyword;
                }
            });

            OrbitInput::get('mall_id', function($mallId) use (&$jsonQuery) {
                if (! empty($mallId)) {
                    $withMallId = array('nested' => array('path' => 'link_to_malls', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_malls.mall_id' => $mallId))))));
                    $jsonQuery['query']['bool']['filter'][] = $withMallId;
                }
             });

            OrbitInput::get('brand_id', function($brandId) use (&$jsonQuery) {
                if (! empty($brandId)) {
                    $withBrandId = array('nested' => array('path' => 'link_to_brands', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_brands.brand_id' => $brandId))))));
                    $jsonQuery['query']['bool']['filter'][] = $withBrandId;
                }
             });

            OrbitInput::get('event_id', function($eventId) use (&$jsonQuery) {
                if (! empty($eventId)) {
                    $withEventId = array('nested' => array('path' => 'link_to_events', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_events.event_id' => $eventId))))));
                    $jsonQuery['query']['bool']['filter'][] = $withEventId;
                }
             });

            OrbitInput::get('promotion_id', function($promotionId) use (&$jsonQuery) {
                if (! empty($promotionId)) {
                    $withPromotionId = array('nested' => array('path' => 'link_to_promotions', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_promotions.promotion_id' => $promotionId))))));
                    $jsonQuery['query']['bool']['filter'][] = $withPromotionId;
                }
             });

            OrbitInput::get('coupon_id', function($couponId) use (&$jsonQuery) {
                if (! empty($couponId)) {
                    $withCouponId = array('nested' => array('path' => 'link_to_coupons', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_coupons.coupon_id' => $couponId))))));
                    $jsonQuery['query']['bool']['filter'][] = $withCouponId;
                }
             });

            // get the category of object



            // filter by category_id
            OrbitInput::get('category_id', function($categoryIds) use (&$jsonQuery, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
                $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.articles.category', '');
                if (! is_array($categoryIds)) {
                    $categoryIds = (array)$categoryIds;
                }

                foreach ($categoryIds as $key => $value) {
                    $categoryFilter['bool']['should'][] = array('match' => array('category_ids' => $value));
                }

                if ($shouldMatch != '') {
                    $categoryFilter['bool']['minimum_should_match'] = $shouldMatch;
                }
                $jsonQuery['query']['bool']['filter'][] = $categoryFilter;
            });

            // filter by country
            $countryCityFilterArr = [];
            $countryData = null;
            OrbitInput::get('country', function ($countryFilter) use (&$jsonQuery, &$countryCityFilterArr, &$countryData) {
                $countryData = Country::select('country_id')->where('name', $countryFilter)->first();

                $countryCityFilterArr['query']['bool'] = ['must' => ['match' => ['country' => $countryFilter]]];
            });

            if (! empty($countryCityFilterArr)) {
                $jsonQuery['query']['bool']['filter'][] = $countryCityFilterArr;
            }

            // sort by name or location
            $defaultSort = $sort = array('published_at' => array('order' => 'desc'));
            if ($sort_by === 'created_date') {
                $sort = array('published_at' => array('order' => $sort_mode));
            } elseif ($sort_by === 'updated_date') {
                $sort = array('updated_at' => array('order' => $sort_mode));
            } else {
                $sort = array('_script' => array('script' => $sortScript, 'type' => 'string', 'order' => $sort_mode));
            }

            $sortByPageType = array();
            $pageTypeScore = '';

            $sortPageScript = "if (doc.containsKey('" . $pageTypeScore . "')) { if(! doc['" . $pageTypeScore . "'].empty) { return doc['" . $pageTypeScore . "'].value } else { return 0}} else {return 0}";
            $sortPage = array('_script' => array('script' => $sortPageScript, 'type' => 'string', 'order' => 'desc'));

            $sortby = array($sortPage, $sort, $defaultSort);
            if ($withScore) {
                $sortby = array($sortPage, "_score", $sort, $defaultSort);
            }
            $jsonQuery["sort"] = $sortby;


            if ($this->withoutScore) {
                // remove _score sort
                $found = FALSE;
                $sortby = array_filter($sortby, function($val) use(&$found) {
                        if ($val === '_score') {
                            $found = $found || TRUE;
                        }
                        return $val !== '_score';
                    });

                if($found) {
                    // redindex array if _score is eliminated
                    $sortby = array_values($sortby);
                }
            }
            $jsonQuery['sort'] = $sortby;

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $esIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.articles.index');
            $locationId = ! empty($mallId) ? $mallId : 0;

            $esParam = [
                'index'  => $esIndex,
                'type'   => Config::get('orbit.elasticsearch.indices.articles.type'),
                'body' => json_encode($jsonQuery)
            ];

            if ($withCache) {
                $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);
                $response = $recordCache->get($serializedCacheKey, function() use ($client, &$esParam) {
                    return $client->search($esParam);
                });
                $recordCache->put($serializedCacheKey, $response);
            } else {
                $response = $client->search($esParam);
            }

            $records = $response['hits']['hits'];

            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');

            // frontend need the mall name
            $mall = null;
            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $data = new \stdclass();
            $data->returned_records = count($records);
            $data->total_records = $response['hits']['total'];
            if (is_object($mall)) {
                $data->mall_name = $mall->name;
                $data->mall_city = $mall->city;
            }
            $data->records = $records;

            $activityNotes = sprintf('Page viewed: Article list');
            $activity->setUser($user)
                ->setActivityName('view_article_main_page')
                ->setActivityNameLong('View Article Main Page')
                ->setObject(null)
                ->setLocation($mall)
                ->setModuleName('Article')
                ->setNotes($activityNotes)
                ->setObjectDisplayName($viewType)
                ->responseOK()
                ->save();

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
}