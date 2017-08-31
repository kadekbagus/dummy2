<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for news list and search in landing page
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
use News;
use Advert;
use NewsMerchant;
use Language;
use Validator;
use PartnerAffectedGroup;
use PartnerCompetitor;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Controller\API\v1\Pub\News\NewsHelper;
use Mall;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use Orbit\Helper\Util\SimpleCache;
use Orbit\Helper\Util\CdnUrlGenerator;
use Elasticsearch\ClientBuilder;
use Carbon\Carbon as Carbon;
use stdClass;
use Country;

class MenuCounterAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $withoutScore = FALSE;

    /**
     * GET - Menu counter in homepage
     *
     * @author Shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string country
     * @param string cities
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMenuCounter()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $keyword = null;
        $user = null;
        $mall = null;

        try {
            $user = $this->getUser();
            $host = Config::get('orbit.elasticsearch');
            $location = OrbitInput::get('location', null);
            $cityFilters = OrbitInput::get('cities', null);
            $countryFilter = OrbitInput::get('country', null);
            $ul = OrbitInput::get('ul', null);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $lon = '';
            $lat = '';

            $prefix = DB::getTablePrefix();

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            //Get now time, time must be 2017-01-09T15:30:00Z
            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateNow = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $campaignJsonQuery = array('from' => 0, 'size' => 1, 'aggs' => array('campaign_index' => array('terms' => array('field' => '_index'))), 'query' => array('bool' => array('filter' => array( array('query' => array('match' => array('status' => 'active'))), array('range' => array('begin_date' => array('lte' => $dateTimeEs))), array('range' => array('end_date' => array('gte' => $dateTimeEs)))))));

            $couponJsonQuery = array('from' => 0, 'size' => 1, 'aggs' => array('campaign_index' => array('terms' => array('field' => '_index'))), 'query' => array('bool' => array('filter' => array( array('query' => array('match' => array('status' => 'active'))), array('range' => array('available' => array('gt' => 0))), array('range' => array('begin_date' => array('lte' => $dateTimeEs))), array('range' => array('end_date' => array('gte' => $dateTimeEs)))))));

            $mallJsonQuery = array('from' => 0, 'size' => 1, 'query' => array('bool' => array('filter' => array( array('query' => array('match' => array('is_subscribed' => 'Y'))), array('query' => array('match' => array('status' => 'active')))))));

            $merchantJsonQuery = array('from' => 0, 'size' => 1);
            $storeJsonQuery = $merchantJsonQuery;

            // get user lat and lon
            if ($location == 'mylocation') {
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

            // filter by location (city or user location)
            OrbitInput::get('location', function($location) use (&$campaignJsonQuery, &$couponJsonQuery, &$mallJsonQuery, $lat, $lon, $distance)
            {
                if (! empty($location)) {

                    if ($location === 'mylocation' && $lat != '' && $lon != '') {
                        $withCache = FALSE;

                        // campaign
                        $campaignLocationFilter = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('geo_distance' => array('distance' => $distance.'km', 'link_to_tenant.position' => array('lon' => $lon, 'lat' => $lat)))))));
                        $campaignJsonQuery['query']['bool']['filter'][] = $campaignLocationFilter;
                        $couponJsonQuery['query']['bool']['filter'][] = $campaignLocationFilter;

                        // mall
                        $mallLocationFilter = array('geo_distance' => array('distance' => $radius.'km', 'position' => array('lon' => $lon, 'lat' => $lat)));
                        $mallJsonQuery['query']['bool']['filter'][] = $mallLocationFilter;
                    } elseif ($location !== 'mylocation') {

                        // campaign
                        $campaignLocationFilter = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.city.raw' => $location))))));
                        $campaignJsonQuery['query']['bool']['filter'][] = $campaignLocationFilter;
                        $couponJsonQuery['query']['bool']['filter'][] = $campaignLocationFilter;

                        // mall
                        $mallLocationFilter = array('match' => array('city' => array('query' => $location, 'operator' => 'and')));
                        $mallJsonQuery['query']['bool']['filter'][] = $mallLocationFilter;
                    }
                }
            });

            $campaignCountryCityFilterArr = [];
            $merchantCountryCityFilterArr = [];
            $storeCountryCityFilterArr = [];
            $countryData = null;
            // filter by country
            OrbitInput::get('country', function ($countryFilter) use (&$campaignJsonQuery, &$mallJsonQuery, &$campaignCountryCityFilterArr, &$countryData, &$merchantCountryCityFilterArr, &$storeCountryCityFilterArr) {
                $countryData = Country::select('country_id')->where('name', $countryFilter)->first();

                // campaign
                $campaignCountryCityFilterArr = ['nested' => ['path' => 'link_to_tenant', 'query' => ['bool' => []], 'inner_hits' => ['name' => 'country_city_hits']]];
                $campaignCountryCityFilterArr['nested']['query']['bool'] = ['must' => ['match' => ['link_to_tenant.country.raw' => $countryFilter]]];

                // mall
                $mallCountryFilterArr = array('match' => array('country.raw' => array('query' => $countryFilter)));;
                $mallJsonQuery['query']['bool']['filter'][] = $mallCountryFilterArr;

                // merchant & store
                $merchantCountryCityFilterArr = ['nested' => ['path' => 'tenant_detail', 'query' => ['bool' => []], 'inner_hits' => ['name' => 'country_city_hits']]];
                $merchantCountryCityFilterArr['nested']['query']['bool'] = ['must' => ['match' => ['tenant_detail.country.raw' => $countryFilter]]];

                $storeCountryCityFilterArr['bool'] = ['must' => ['match' => ['country.raw' => $countryFilter]]];
            });

            // filter by city, only filter when countryFilter is not empty
            OrbitInput::get('cities', function ($cityFilters) use (&$campaignJsonQuery, &$mallJsonQuery, $countryFilter, &$campaignCountryCityFilterArr, &$merchantCountryCityFilterArr, &$storeCountryCityFilterArr) {
                if (! empty($countryFilter)) {
                    $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.news.city', '');
                    $campaignCityFilterArr = [];
                    $mallCityFilterArr = [];
                    $merchantCityFilterArr = [];
                    $storeCityFilterArr = [];
                    foreach ((array) $cityFilters as $cityFilter) {
                        $campaignCityFilterArr[] = ['match' => ['link_to_tenant.city.raw' => $cityFilter]];
                        $mallCityFilterArr['bool']['should'][] = array('match' => array('city.raw' => array('query' => $cityFilter)));
                        $merchantCityFilterArr[] = ['match' => ['tenant_detail.city.raw' => $cityFilter]];
                        $storeCityFilterArr[] = ['match' => ['city.raw' => $cityFilter]];
                    }

                    if ($shouldMatch != '') {
                        if (count((array) $cityFilters) === 1) {
                            // if user just filter with one city, value of should match must be 100%
                            $shouldMatch = '100%';
                        }
                        $campaignCountryCityFilterArr['nested']['query']['bool']['minimum_should_match'] = $shouldMatch;
                        $mallCityFilterArr['bool']['minimum_should_match'] = $shouldMatch;
                        $merchantCountryCityFilterArr['nested']['query']['bool']['minimum_should_match'] = $shouldMatch;
                        $storeCountryCityFilterArr['bool']['minimum_should_match'] = $shouldMatch;
                    }

                    $campaignCountryCityFilterArr['nested']['query']['bool']['should'] = $campaignCityFilterArr;
                    $mallJsonQuery['query']['bool']['filter'][] = $mallCityFilterArr;
                    $merchantCountryCityFilterArr['nested']['query']['bool']['should'] = $merchantCityFilterArr;
                    $storeCountryCityFilterArr['bool']['should'] = $storeCityFilterArr;
                }
            });

            if (! empty($campaignCountryCityFilterArr)) {
                $campaignJsonQuery['query']['bool']['filter'][] = $campaignCountryCityFilterArr;
                $couponJsonQuery['query']['bool']['filter'][] = $campaignCountryCityFilterArr;
            }

            if (! empty($merchantCountryCityFilterArr)) {
                $merchantJsonQuery['query']['bool']['must'][] = $merchantCountryCityFilterArr;
            }

            if (! empty($storeCountryCityFilterArr)) {
                $storeJsonQuery['query']['bool']['must'][] = $storeCountryCityFilterArr;
            }

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $newsIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.news.index');
            $promotionIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.promotions.index');
            $couponIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.coupons.index');
            $mallIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.malldata.index');
            $merchantIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.stores.index', 'stores');
            $storeIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.store_details.index', 'store_details');

            // call es campaign
            $campaignParam = [
                'index'  => $newsIndex . ',' . $promotionIndex,
                'type'   => Config::get('orbit.elasticsearch.indices.news.type'),
                'body' => json_encode($campaignJsonQuery)
            ];
            $campaignResponse = $client->search($campaignParam);

            $couponParam = [
                'index'  => $couponIndex,
                'type'   => Config::get('orbit.elasticsearch.indices.news.type'),
                'body' => json_encode($couponJsonQuery)
            ];
            $couponResponse = $client->search($couponParam);

            // call es mall
            $mallParam = [
                'index'  => $mallIndex,
                'type'   => Config::get('orbit.elasticsearch.indices.malldata.type'),
                'body' => json_encode($mallJsonQuery)
            ];
            $mallResponse = $client->search($mallParam);

            // merchant
            $merchantParam = [
                'index'  => $merchantIndex,
                'type'   => Config::get('orbit.elasticsearch.indices.stores.type'),
                'body' => json_encode($merchantJsonQuery)
            ];
            $merchantResponse = $client->search($merchantParam);

            // store
            $storeParam = [
                'index'  => $storeIndex,
                'type'   => Config::get('orbit.elasticsearch.indices.store_details.type'),
                'body' => json_encode($storeJsonQuery)
            ];
            $storeResponse = $client->search($storeParam);

            $campaignRecords = $campaignResponse['aggregations']['campaign_index']['buckets'];
            $couponRecords = $couponResponse['aggregations']['campaign_index']['buckets'];
            $listOfRec = array();
            $listOfRec['promotions'] = 0;
            $listOfRec['coupons'] = 0;
            $listOfRec['news'] = 0;

            foreach ($campaignRecords as $campaign) {
                $key = str_replace($esPrefix, '', $campaign['key']);
                $listOfRec[$key] = $campaign['doc_count'];
            }

            foreach ($couponRecords as $coupon) {
                $key = str_replace($esPrefix, '', $coupon['key']);
                $listOfRec[$key] = $coupon['doc_count'];
            }

            $listOfRec['mall'] = empty($mallResponse['hits']['total']) ? 0 : $mallResponse['hits']['total'];
            $listOfRec['merchants'] = empty($merchantResponse['hits']['total']) ? 0 : $merchantResponse['hits']['total'];
            $listOfRec['stores'] = empty($storeResponse['hits']['total']) ? 0 : $storeResponse['hits']['total'];

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = count($listOfRec);
            $data->records = $listOfRec;

            if (OrbitInput::get('from_homepage', '') !== 'y') {
                if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                    if (is_object($mall)) {
                        $activityNotes = sprintf('Page viewed: View mall event list');
                        $activity->setUser($user)
                            ->setActivityName('view_mall_event_list')
                            ->setActivityNameLong('View mall event list')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('News')
                            ->setNotes($activityNotes)
                            ->responseOK()
                            ->save();
                    } else {
                        $activityNotes = sprintf('Page viewed: News list');
                        $activity->setUser($user)
                            ->setActivityName('view_news_main_page')
                            ->setActivityNameLong('View News Main Page')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('News')
                            ->setNotes($activityNotes)
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
}