<?php namespace Orbit\Controller\API\v1\Pub\Sponsor;
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
use UserSponsor;
use Activity;
use Language;
use Validator;
use Elasticsearch\ClientBuilder;
use Carbon\Carbon as Carbon;
use stdClass;
use Country;

class UserSponsorCampaignAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $withoutScore = FALSE;

    /**
     * GET - total campaign that link to user sponsor (ewallet and credit card)
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
    public function getUserSponsorCampaign()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $keyword = null;
        $user = null;

        try {
            $user = $this->getUser();
            $role = $user->role->role_name;
            $host = Config::get('orbit.elasticsearch');
            $location = OrbitInput::get('location', null);
            $cityFilters = OrbitInput::get('cities', null);
            $countryFilter = OrbitInput::get('country', null);
            $ul = OrbitInput::get('ul', null);
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $lon = '';
            $lat = '';
            $mallId = OrbitInput::get('mall_id', null);

            $prefix = DB::getTablePrefix();

            // get user sponsor (ewallet and credit card)
            $userId = $user->user_id;
            $userSponsor = array();

            // get user ewallet
            $userEwallet = UserSponsor::select('sponsor_providers.sponsor_provider_id as ewallet_id')
                                      ->join('sponsor_providers', 'sponsor_providers.sponsor_provider_id', '=', 'user_sponsor.sponsor_id')
                                      ->where('user_sponsor.sponsor_type', 'ewallet')
                                      ->where('sponsor_providers.status', 'active')
                                      ->where('user_sponsor.user_id', $userId)
                                      ->get();

            if (! $userEwallet->isEmpty()) {
              foreach ($userEwallet as $ewallet) {
                $userSponsor[] = $ewallet->ewallet_id;
              }
            }

            $userCreditCard = UserSponsor::select('sponsor_credit_cards.sponsor_credit_card_id as credit_card_id')
                                      ->join('sponsor_credit_cards', 'sponsor_credit_cards.sponsor_credit_card_id', '=', 'user_sponsor.sponsor_id')
                                      ->join('sponsor_providers', 'sponsor_providers.sponsor_provider_id', '=', 'sponsor_credit_cards.sponsor_provider_id')
                                      ->where('user_sponsor.sponsor_type', 'credit_card')
                                      ->where('sponsor_credit_cards.status', 'active')
                                      ->where('sponsor_providers.status', 'active')
                                      ->where('user_sponsor.user_id', $userId)
                                      ->get();

            if (! $userCreditCard->isEmpty()) {
              foreach ($userCreditCard as $creditCard) {
                $userSponsor[] = $creditCard->credit_card_id;
              }
            }

            $listOfRec = array();
            $listOfRec['promotions'] = 0;
            $listOfRec['coupons'] = 0;
            $listOfRec['news'] = 0;

            if ((strtolower($role) === 'consumer') && (! empty($userSponsor))) {
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

              if (! empty($userSponsor)) {
                  $withSponsorProviderIds = array('nested' => array('path' => 'sponsor_provider', 'query' => array('filtered' => array('filter' => array('terms' => array('sponsor_provider.sponsor_id' => $userSponsor))))));
                  $campaignJsonQuery['query']['bool']['filter'][] = $withSponsorProviderIds;
                  $couponJsonQuery['query']['bool']['filter'][] = $withSponsorProviderIds;
              }

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

              OrbitInput::get('mall_id', function($mallId) use (&$campaignJsonQuery, &$couponJsonQuery) {
                if (! empty($mallId)) {
                    $withMallId = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.parent_id' => $mallId))))));
                    $campaignJsonQuery['query']['bool']['filter'][] = $withMallId;
                    $couponJsonQuery['query']['bool']['filter'][] = $withMallId;
                }
             });

              // filter by location (city or user location)
              OrbitInput::get('location', function($location) use (&$campaignJsonQuery, &$couponJsonQuery, $lat, $lon, $distance)
              {
                  if (! empty($location)) {

                      if ($location === 'mylocation' && $lat != '' && $lon != '') {
                          $withCache = FALSE;

                          // campaign
                          $campaignLocationFilter = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('geo_distance' => array('distance' => $distance.'km', 'link_to_tenant.position' => array('lon' => $lon, 'lat' => $lat)))))));
                          $campaignJsonQuery['query']['bool']['filter'][] = $campaignLocationFilter;
                          $couponJsonQuery['query']['bool']['filter'][] = $campaignLocationFilter;
                      } elseif ($location !== 'mylocation') {

                          // campaign
                          $campaignLocationFilter = array('nested' => array('path' => 'link_to_tenant', 'query' => array('filtered' => array('filter' => array('match' => array('link_to_tenant.city.raw' => $location))))));
                          $campaignJsonQuery['query']['bool']['filter'][] = $campaignLocationFilter;
                          $couponJsonQuery['query']['bool']['filter'][] = $campaignLocationFilter;
                      }
                  }
              });

              $campaignCountryCityFilterArr = [];
              $countryData = null;
              // filter by country
              OrbitInput::get('country', function ($countryFilter) use (&$campaignJsonQuery, &$campaignCountryCityFilterArr, &$countryData) {
                  $countryData = Country::select('country_id')->where('name', $countryFilter)->first();

                  // campaign
                  $campaignCountryCityFilterArr = ['nested' => ['path' => 'link_to_tenant', 'query' => ['bool' => []], 'inner_hits' => ['name' => 'country_city_hits']]];
                  $campaignCountryCityFilterArr['nested']['query']['bool'] = ['must' => ['match' => ['link_to_tenant.country.raw' => $countryFilter]]];
              });

              // filter by city, only filter when countryFilter is not empty
              OrbitInput::get('cities', function ($cityFilters) use (&$campaignJsonQuery, $countryFilter, &$campaignCountryCityFilterArr) {
                  if (! empty($countryFilter)) {
                      $shouldMatch = Config::get('orbit.elasticsearch.minimum_should_match.news.city', '');
                      $campaignCityFilterArr = [];
                      foreach ((array) $cityFilters as $cityFilter) {
                          $campaignCityFilterArr[] = ['match' => ['link_to_tenant.city.raw' => $cityFilter]];
                      }

                      if ($shouldMatch != '') {
                          if (count((array) $cityFilters) === 1) {
                              // if user just filter with one city, value of should match must be 100%
                              $shouldMatch = '100%';
                          }
                          $campaignCountryCityFilterArr['nested']['query']['bool']['minimum_should_match'] = $shouldMatch;
                      }

                      $campaignCountryCityFilterArr['nested']['query']['bool']['should'] = $campaignCityFilterArr;
                  }
              });

              if (! empty($campaignCountryCityFilterArr)) {
                  $campaignJsonQuery['query']['bool']['filter'][] = $campaignCountryCityFilterArr;
                  $couponJsonQuery['query']['bool']['filter'][] = $campaignCountryCityFilterArr;
              }

              $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
              $newsIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.news.index');
              $promotionIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.promotions.index');
              $couponIndex = $esPrefix . Config::get('orbit.elasticsearch.indices.coupons.index');

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

              $campaignRecords = $campaignResponse['aggregations']['campaign_index']['buckets'];
              $couponRecords = $couponResponse['aggregations']['campaign_index']['buckets'];

              foreach ($campaignRecords as $campaign) {
                  $key = str_replace($esPrefix, '', $campaign['key']);
                  $listOfRec[$key] = $campaign['doc_count'];
              }

              foreach ($couponRecords as $coupon) {
                  $key = str_replace($esPrefix, '', $coupon['key']);
                  $listOfRec[$key] = $coupon['doc_count'];
              }
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = count($listOfRec);
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