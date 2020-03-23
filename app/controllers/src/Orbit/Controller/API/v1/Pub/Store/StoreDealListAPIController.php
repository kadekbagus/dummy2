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
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Util\CdnUrlGeneratorWithCloudfront;
use Elasticsearch\ClientBuilder;
use Config;
use Mall;
use News;
use Tenant;
use Advert;
use stdClass;
use DB;
use Validator;
use Language;
use Coupon;
use Activity;
use Lang;
use \Carbon\Carbon as Carbon;


class StoreDealListAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $store = NULL;
    protected $withoutScore = FALSE;

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
    public function getCampaignStoreDeal()
    {
        $httpCode = 200;
        try {
            $sort_by = OrbitInput::get('sortby', 'campaign_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $merchant_id = OrbitInput::get('merchant_id');
            $store_name = OrbitInput::get('store_name', '');
            $keyword = OrbitInput::get('keyword');
            $language = OrbitInput::get('language', 'id');
            $location = OrbitInput::get('location', null);
            $countryFilter = OrbitInput::get('country', null);
            $citiesFilter = OrbitInput::get('cities', null);
            $category_id = OrbitInput::get('category_id');
            $token = OrbitInput::get('token');
            $ul = OrbitInput::get('ul', null);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $take = PaginationNumber::parseTakeFromGet('campaign');
            $skip = PaginationNumber::parseSkipFromGet();
            $lon = '';
            $lat = '';
            $host = Config::get('orbit.elasticsearch');
            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $campaignType = OrbitInput::get('campaign_type', '');

            // Call validation from store helper
            $this->registerCustomValidation();
            // $storeHelper = StoreHelper::create();
            // $storeHelper->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    'language' => $language,
                    'sortby'   => $sort_by,
                ),
                array(
                    'merchant_id' => 'required|orbit.empty.tenant',
                    'language'    => 'required|orbit.empty.language_default',
                    'sortby'      => 'in:campaign_name,name,location,created_date',
                ),
                array(
                    'required'           => 'Merchant id is required',
                    'orbit.empty.tenant' => Lang::get('validation.orbit.empty.tenant'),
                )
            );
// print_r("expression"); die();
            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();

            $store = Tenant::select('merchants.merchant_id', 'merchants.name', DB::raw('oms.country_id'), 'countries.name as country_name')
                        ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                        ->leftJoin('countries', 'countries.country_id', '=', DB::raw('oms.country_id'))
                        ->where('merchants.merchant_id', $merchant_id)
                        ->where('merchants.status', '=', 'active')
                        ->where(DB::raw('oms.status'), '=', 'active')
                        ->first();

            $country_id = '';
            $country_name = '';
            $store_name = '';
            if (! empty($store)) {
                $store_name = $store->name;
                $country_id = $store->country_id;
                $country_name = $store->country_name;
            }

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $jsonQuery = array('from' => $skip, 'size' => $take, 'query' => array('bool' => array('must' => array( array('query' => array('match' => array('status' => 'active'))), array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.name.raw' => $store_name)))))), array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.country' => $country_name)))))), array('range' => array('begin_date' => array('lte' => $dateTimeEs))), array('range' => array('end_date' => array('gte' => $dateTimeEs)))))));

            // get user lat and lon
            if ($sort_by == 'location' || $location == 'mylocation') {
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
            }

            OrbitInput::get('mall_id', function($mallId) use (&$jsonQuery) {
                if (! empty($mallId)) {
                    $withMallId = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.parent_id' => $mallId))))));
                    $jsonQuery['query']['bool']['must'][] = $withMallId;
                }
             });

            // filter by category_id
            OrbitInput::get('category_id', function($categoryIds) use (&$jsonQuery, &$searchFlag) {
                $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.promotion.category', '');
                $searchFlag = $searchFlag || TRUE;
                if (! is_array($categoryIds)) {
                    $categoryIds = (array)$categoryIds;
                }

                foreach ($categoryIds as $key => $value) {
                    $categoryFilter['bool']['should'][] = array('match' => array('category_ids' => $value));
                }

                if ($shouldMatch != '') {
                    $categoryFilter['bool']['minimum_should_match'] = $shouldMatch;
                }
                $jsonQuery['query']['bool']['must'][] = $categoryFilter;
            });

            $countryCityFilterArr = [];
            // filter by country
            OrbitInput::get('country', function ($countryFilter) use (&$jsonQuery, &$countryCityFilterArr) {
                $countryCityFilterArr = ['nested' => ['path' => 'link_to_tenant', 'query' => ['bool' => []], 'inner_hits' => ['name' => 'country_city_hits']]];

                $countryCityFilterArr['nested']['query']['bool'] = ['must' => ['match' => ['link_to_tenant.country.raw' => $countryFilter]]];
            });

            // filter by city, only filter when countryFilter is not empty
            OrbitInput::get('cities', function ($cityFilters) use (&$jsonQuery, $countryFilter, &$countryCityFilterArr) {
                if (! empty($countryFilter)) {
                    $cityFilterArr = [];
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.promotion.city', '');
                    foreach ((array) $cityFilters as $cityFilter) {
                        $cityFilterArr[] = ['match' => ['link_to_tenant.city.raw' => $cityFilter]];
                    }

                    if ($shouldMatch != '') {
                        if (count((array) $cityFilters) === 1) {
                            // if user just filter with one city, value of should match must be 100%
                            $shouldMatch = '100%';
                        }
                        $countryCityFilterArr['nested']['query']['bool']['minimum_should_match'] = $shouldMatch;
                    }
                    $countryCityFilterArr['nested']['query']['bool']['should'] = $cityFilterArr;
                }
            });

            if (! empty($countryCityFilterArr)) {
                $jsonQuery['query']['bool']['must'][] = $countryCityFilterArr;
            }

            // filter by campaign type
            switch ($campaignType) {
                case 'promotion':
                    $indexSearch = $esPrefix . Config::get('orbit.elasticsearch.indices.promotions.index');
                    break;

                case 'news':
                    $indexSearch = $esPrefix . Config::get('orbit.elasticsearch.indices.news.index');
                    break;

                case 'coupon':
                    $indexSearch = $esPrefix . Config::get('orbit.elasticsearch.indices.coupons.index');
                    break;

                default:
                    $indexSearch = $esPrefix . Config::get('orbit.elasticsearch.indices.promotions.index') . ',' . $esPrefix . Config::get('orbit.elasticsearch.indices.news.index') . ',' . $esPrefix . Config::get('orbit.elasticsearch.indices.coupons.index');
                    break;
            }

            $esParam = [
                'index'  => $indexSearch,
                'type'   => Config::get('orbit.elasticsearch.indices.promotions.type'),
                'body' => json_encode($jsonQuery)
            ];

            $response = $client->search($esParam);
            $records = $response['hits'];
            $totalRec = $records['total'];

            $listOfRec = array();
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGeneratorWithCloudfront::create(['cdn' => $cdnConfig], 'cdn');

            foreach ($records['hits'] as $record) {
                $data = array();
                $default_lang = '';
                foreach ($record['_source'] as $key => $value) {
                    if ($key === 'promotion_id' || $key === 'news_id') {
                        $key = 'campaign_id';
                    }

                    $data[$key] = $value;
                    $default_lang = (empty($record['_source']['default_lang']))? '' : $record['_source']['default_lang'];

                    // translation, to get name, desc and image
                    if ($key === "translation") {
                        $data['image_url'] = '';

                        foreach ($record['_source']['translation'] as $dt) {
                            $localPath = (! empty($dt['image_url'])) ? $dt['image_url'] : '';
                            $cdnPath = (! empty($dt['image_cdn_url'])) ? $dt['image_cdn_url'] : '';

                            if ($dt['language_code'] === $language) {
                                // name
                                if (! empty($dt['name'])) {
                                    $data['name'] = $dt['name'];
                                }

                                // desc
                                if (! empty($dt['description'])) {
                                    $data['description'] = $dt['description'];
                                }

                                // image
                                if (! empty($dt['image_url'])) {
                                    $data['image_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
                                }
                            } elseif ($dt['language_code'] === $default_lang) {
                                // name
                                if (! empty($dt['name']) && empty($data['name'])) {
                                    $data['name'] = $dt['name'];
                                }

                                // description
                                if (! empty($dt['description']) && empty($data['description'])) {
                                    $data['description'] = $dt['description'];
                                }

                                // image
                                if (empty($data['image_url'])) {
                                    $data['image_url'] = $imgUrl->getImageUrl($localPath, $cdnPath);
                                }
                            }
                        }
                    }
                }

                unset($data['category_ids'], $data['translation'], $data['link_to_tenant'], $data['keywords'], $data['partner_ids'], $data['partner_tokens'], $data['advert_ids'], $data['mall_page_views'], $data['gtm_page_views']);
                $listOfRec[] = $data;
            }

            $this->response->data = new stdClass();
            $this->response->data->total_records = $totalRec;
            $this->response->data->returned_records = count($response['hits']['hits']);
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

}
