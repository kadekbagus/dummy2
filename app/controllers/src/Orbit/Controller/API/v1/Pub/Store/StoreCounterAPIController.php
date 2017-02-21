<?php namespace Orbit\Controller\API\v1\Pub\Store;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use Validator;
use Language;
use \Carbon\Carbon as Carbon;
use Orbit\Helper\Util\SimpleCache;
use Elasticsearch\ClientBuilder;
use Lang;
use PartnerAffectedGroup;
use PartnerCompetitor;

class StoreCounterAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $store = NULL;
    protected $withoutScore = FALSE;

    /**
     * GET - get all store in all mall, group by name
     *
     * @author Ahmad <ahmad@dominopos.com>
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
    public function getStoreCount()
    {
        $httpCode = 200;

        $this->cacheKey = [];
        $this->serializedCacheKey = [];

        // Cache result of all possible calls to backend storage
        $this->cacheConfig = Config::get('orbit.cache.context');
        $this->cacheContext = 'store-counter';
        $this->recordCache = SimpleCache::create($this->cacheConfig, $this->cacheContext);
        $this->countCache = SimpleCache::create($this->cacheConfig, $this->cacheContext)
                                       ->setKeyPrefix($this->cacheContext);

        try {
            $this->user = $this->getUser();
            $this->host = Config::get('orbit.elasticsearch');
            $this->sort_by = OrbitInput::get('sortby', 'name');
            $this->sort_mode = OrbitInput::get('sortmode','asc');
            $this->location = OrbitInput::get('location', null);
            $this->cityFilters = OrbitInput::get('cities', null);
            $this->countryFilter = OrbitInput::get('country', null);
            $this->usingDemo = Config::get('orbit.is_demo', FALSE);
            $this->language = OrbitInput::get('language', 'id');
            $this->userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $this->distance = Config::get('orbit.geo_location.distance', 10);
            $this->ul = OrbitInput::get('ul');
            $this->lon = 0;
            $this->lat = 0;
            $this->list_type = OrbitInput::get('list_type', 'preferred');
            $this->from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $this->category_id = OrbitInput::get('category_id');
            $this->mallId = OrbitInput::get('mall_id', null);
            $this->no_total_records = OrbitInput::get('no_total_records', null);
            $this->take = PaginationNumber::parseTakeFromGet('retailer');
            $this->skip = PaginationNumber::parseSkipFromGet();
            $withCache = TRUE;

            // store can not sorted by date, so it must be changes to default sorting (name - ascending)
            if ($this->sort_by === "created_date") {
                $this->sort_by = "name";
                $sort_mode = "asc";
            }

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'language' => $this->language,
                    'sortby'   => $this->sort_by,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,location,updated_date',
                )
            );

            // Pass all possible parameters to be used as cache key.
            // Make sure there is no missing one.
            $this->cacheKey = [
                'sort_by' => $this->sort_by, 'sort_mode' => $this->sort_mode, 'language' => $this->language,
                'location' => $this->location,
                'user_location_cookie_name' => isset($_COOKIE[$this->userLocationCookieName]) ? $_COOKIE[$this->userLocationCookieName] : NULL,
                'distance' => $this->distance, 'mall_id' => $this->mallId,
                'list_type' => $this->list_type,
                'from_mall_ci' => $this->from_mall_ci, 'category_id' => $this->category_id,
                'no_total_record' => $this->no_total_records,
                'take' => $this->take, 'skip' => $this->skip,
                'country' => $this->countryFilter, 'cities' => $this->cityFilters,
            ];

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $counter = new \stdClass();
            $counter->total_merchant = 0;
            $counter->total_store = 0;

            $response = $this->countCache->get($this->serializedCacheKey, function() use (&$counter) {
                $counter->total_merchant = $this->getTotalMerchant();
                $counter->total_store = $this->getTotalStore();
                return $counter;
            });
            $this->countCache->put($this->serializedCacheKey, $response);

            $data = new \stdClass();
            $data->total_merchant = $counter->total_merchant;
            $data->total_store = $counter->total_store;

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

    protected function getTotalMerchant()
    {
        $totalMerchant = 0;
        try {
            $valid_language = $this->valid_language;

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($this->host['hosts']) // Set the hosts
                    ->build();

            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $withScore = false;
            $esTake = 0;

            // value will be true if query to nested, *to get right number of stores
            $withInnerHits = false;
            $innerHitsCountry = false;
            $innerHitsCity = false;

            $jsonQuery = array('from' => $this->skip, 'size' => $esTake);

            $withKeywordSearch = false;
            OrbitInput::get('keyword', function($keyword) use (&$jsonQuery, &$withScore, &$withKeywordSearch)
            {
                $this->cacheKey['keyword'] = $keyword;
                if ($keyword != '') {
                    $keyword = strtolower($keyword);
                    $withScore = true;
                    $withKeywordSearch = true;

                    $priority['name'] = Config::get('orbit.elasticsearch.priority.store.name', '^6');
                    $priority['object_type'] = Config::get('orbit.elasticsearch.priority.store.object_type', '^5');
                    $priority['mall_name'] = Config::get('orbit.elasticsearch.priority.store.mall_name', '^4');
                    $priority['city'] = Config::get('orbit.elasticsearch.priority.store.city', '^3');
                    $priority['province'] = Config::get('orbit.elasticsearch.priority.store.province', '^2');
                    $priority['keywords'] = Config::get('orbit.elasticsearch.priority.store.keywords', '');
                    $priority['address_line'] = Config::get('orbit.elasticsearch.priority.store.address_line', '');
                    $priority['country'] = Config::get('orbit.elasticsearch.priority.store.country', '');
                    $priority['description'] = Config::get('orbit.elasticsearch.priority.store.description', '');

                    $filterTranslation = array('nested' => array('path' => 'translation', 'query' => array('multi_match' => array('query' => $keyword, 'fields' => array('translation.description'.$priority['description'])))));
                    $jsonQuery['query']['filtered']['query']['bool']['should'][] = $filterTranslation;

                    $filterDetail = array('nested' => array('path' => 'tenant_detail', 'query' => array('multi_match' => array('query' => $keyword, 'fields' => array('tenant_detail.city'.$priority['city'], 'tenant_detail.province'.$priority['province'], 'tenant_detail.country'.$priority['country'], 'tenant_detail.mall_name'.$priority['mall_name'])))));
                    $jsonQuery['query']['filtered']['query']['bool']['should'][] = $filterDetail;

                    $filterKeyword = array('multi_match' => array('query' => $keyword, 'fields' => array('name'.$priority['name'],'object_type'.$priority['object_type'], 'keywords'.$priority['keywords'])));
                    $jsonQuery['query']['filtered']['query']['bool']['should'][] = $filterKeyword;
                }
            });

            OrbitInput::get('mall_id', function($mallId) use (&$jsonQuery, &$withInnerHits) {
                if (! empty($mallId)) {
                    $withInnerHits = true;
                    $withMallId = array('nested' => array('path' => 'tenant_detail', 'query' => array('filtered' => array('filter' => array('match' => array('tenant_detail.mall_id' => $mallId)))), 'inner_hits' => new stdclass()));
                    $jsonQuery['query']['filtered']['filter']['and'][] = $withMallId;
                }
             });

            // filter by category_id
            OrbitInput::get('category_id', function($categoryIds) use (&$jsonQuery) {
                if (! is_array($categoryIds)) {
                    $categoryIds = (array)$categoryIds;
                }

                foreach ($categoryIds as $key => $value) {
                    $categoryFilter['or'][] = array('match' => array('category' => $value));
                }
                $jsonQuery['query']['filtered']['filter']['and'][] = $categoryFilter;
            });

            OrbitInput::get('partner_id', function($partnerId) use (&$jsonQuery) {
                $this->cacheKey['partner_id'] = $partnerId;
                $partnerFilter = '';
                if (! empty($partnerId)) {
                    $partnerAffected = PartnerAffectedGroup::join('affected_group_names', function($join) {
                                                                $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                                                                     ->where('affected_group_names.group_type', '=', 'tenant');
                                                            })
                                                            ->where('partner_id', $partnerId)
                                                            ->first();

                    if (is_object($partnerAffected)) {
                        $exception = Config::get('orbit.partner.exception_behaviour.partner_ids', []);
                        $partnerFilter = array('query' => array('match' => array('partner_ids' => $partnerId)));

                        if (in_array($partnerId, $exception)) {
                            $partnerIds = PartnerCompetitor::where('partner_id', $partnerId)->lists('competitor_id');
                            $partnerFilter = array('query' => array('not' => array('terms' => array('partner_ids' => $partnerIds))));
                        }
                        $jsonQuery['query']['filtered']['filter']['and'][] = $partnerFilter;
                    }
                }
            });

            $countryCityFilterArr = [];

            // filter by country
            OrbitInput::get('country', function ($countryFilter) use (&$jsonQuery, &$withInnerHits, &$innerHitsCity, &$countryCityFilterArr) {
                $withInnerHits = true;
                $innerHitsCity = true;

                $countryCityFilterArr = ['nested' => ['path' => 'tenant_detail', 'query' => ['bool' => []], 'inner_hits' => ['name' => 'country_city_hits']]];

                $countryCityFilterArr['nested']['query']['bool'] = ['must' => ['match' => ['tenant_detail.country.raw' => $countryFilter]]];
            });

            // filter by city, only filter when countryFilter is not empty
            OrbitInput::get('cities', function ($cityFilters) use (&$jsonQuery, &$countryCityFilterArr) {
                if (! empty($this->countryFilter)) {
                    $cityFilterArr = [];
                    foreach ((array) $cityFilters as $cityFilter) {
                        $cityFilterArr[] = ['match' => ['tenant_detail.city.raw' => $cityFilter]];
                    }
                    $countryCityFilterArr['nested']['query']['bool']['should'] = $cityFilterArr;
                }
            });

            if (! empty($countryCityFilterArr)) {
                $jsonQuery['query']['filtered']['filter']['and'][] = $countryCityFilterArr;
            }

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');

            $esParam = [
                'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.stores.index', 'stores'),
                'type'   => Config::get('orbit.elasticsearch.indices.stores.type', 'basic'),
                'body' => json_encode($jsonQuery)
            ];

            $response = $client->search($esParam);

            if (isset($response['hits'])) {
                $records = $response['hits'];

                $totalMerchant = $records['total'];
            }

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

        return $totalMerchant;
    }

    protected function getTotalStore()
    {
        $totalStore = 0;

        try {
            $valid_language = $this->valid_language;

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($this->host['hosts']) // Set the hosts
                    ->build();

            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $withScore = false;
            $esTake = 0;

            // value will be true if query to nested, *to get right number of stores
            $withInnerHits = false;
            $innerHitsCountry = false;
            $innerHitsCity = false;

            $jsonQuery = array('from' => $this->skip, 'size' => $esTake);

            $withKeywordSearch = false;
            OrbitInput::get('keyword', function($keyword) use (&$jsonQuery, &$withScore, &$withKeywordSearch)
            {
                $this->cacheKey['keyword'] = $keyword;
                if ($keyword != '') {
                    $keyword = strtolower($keyword);
                    $withScore = true;
                    $withKeywordSearch = true;

                    $priority['name'] = Config::get('orbit.elasticsearch.priority.store.name', '^6');
                    $priority['object_type'] = Config::get('orbit.elasticsearch.priority.store.object_type', '^5');
                    $priority['mall_name'] = Config::get('orbit.elasticsearch.priority.store.mall_name', '^4');
                    $priority['city'] = Config::get('orbit.elasticsearch.priority.store.city', '^3');
                    $priority['province'] = Config::get('orbit.elasticsearch.priority.store.province', '^2');
                    $priority['keywords'] = Config::get('orbit.elasticsearch.priority.store.keywords', '');
                    $priority['address_line'] = Config::get('orbit.elasticsearch.priority.store.address_line', '');
                    $priority['country'] = Config::get('orbit.elasticsearch.priority.store.country', '');
                    $priority['description'] = Config::get('orbit.elasticsearch.priority.store.description', '');

                    $filterKeyword = array('multi_match' => array('query' => $keyword, 'fields' => array('name' . $priority['name'],'object_type' . $priority['object_type'], 'keywords' . $priority['keywords'], 'description' . $priority['description'], 'city' . $priority['city'], 'province' . $priority['province'], 'country' . $priority['country'], 'mall_name' . $priority['mall_name'])));
                    $jsonQuery['query']['filtered']['query']['bool']['should'][] = $filterKeyword;
                }
            });

            OrbitInput::get('mall_id', function($mallId) use (&$jsonQuery) {
                if (! empty($mallId)) {
                    $withMallId = array('match' => array('mall_id' => $mallId));
                    $jsonQuery['query']['filtered']['filter']['and'][] = $withMallId;
                }
             });

            // filter by category_id
            OrbitInput::get('category_id', function($categoryIds) use (&$jsonQuery) {
                if (! is_array($categoryIds)) {
                    $categoryIds = (array)$categoryIds;
                }

                foreach ($categoryIds as $key => $value) {
                    $categoryFilter['or'][] = array('match' => array('category' => $value));
                }
                $jsonQuery['query']['filtered']['filter']['and'][] = $categoryFilter;
            });

            OrbitInput::get('partner_id', function($partnerId) use (&$jsonQuery) {
                $this->cacheKey['partner_id'] = $partnerId;
                $partnerFilter = '';
                if (! empty($partnerId)) {
                    $partnerAffected = PartnerAffectedGroup::join('affected_group_names', function($join) {
                                                                $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                                                                     ->where('affected_group_names.group_type', '=', 'tenant');
                                                            })
                                                            ->where('partner_id', $partnerId)
                                                            ->first();

                    if (is_object($partnerAffected)) {
                        $exception = Config::get('orbit.partner.exception_behaviour.partner_ids', []);
                        $partnerFilter = array('query' => array('match' => array('partner_ids' => $partnerId)));

                        if (in_array($partnerId, $exception)) {
                            $partnerIds = PartnerCompetitor::where('partner_id', $partnerId)->lists('competitor_id');
                            $partnerFilter = array('query' => array('not' => array('terms' => array('partner_ids' => $partnerIds))));
                        }
                        $jsonQuery['query']['filtered']['filter']['and'][] = $partnerFilter;
                    }
                }
            });

            $countryCityFilterArr = [];

            // filter by country
            OrbitInput::get('country', function ($countryFilter) use (&$jsonQuery, &$withInnerHits, &$innerHitsCity, &$countryCityFilterArr) {
                $withInnerHits = true;
                $innerHitsCity = true;

                $countryCityFilterArr['bool'] = ['must' => ['match' => ['country.raw' => $countryFilter]]];
            });

            // filter by city, only filter when countryFilter is not empty
            OrbitInput::get('cities', function ($cityFilters) use (&$jsonQuery, &$countryCityFilterArr) {
                if (! empty($this->countryFilter)) {
                    $cityFilterArr = [];
                    foreach ((array) $cityFilters as $cityFilter) {
                        $cityFilterArr[] = ['match' => ['city.raw' => $cityFilter]];
                    }
                    $countryCityFilterArr['bool']['should'] = $cityFilterArr;
                }
            });

            if (! empty($countryCityFilterArr)) {
                $jsonQuery['query']['filtered']['filter']['and'][] = $countryCityFilterArr;
            }

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');

            $esParam = [
                'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.store_details.index', 'store_details'),
                'type'   => Config::get('orbit.elasticsearch.indices.store_details.type', 'basic'),
                'body' => json_encode($jsonQuery)
            ];
// dd(json_encode($jsonQuery));
            $response = $client->search($esParam);

            if (isset($response['hits'])) {
                $records = $response['hits'];

                $totalStore = $records['total'];
            }

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

        return $totalStore;
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
}
