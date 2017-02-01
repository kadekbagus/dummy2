<?php namespace Orbit\Controller\API\v1\Pub;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\OrbitShopAPI;
use \Config;
use \Exception;
use Orbit\Controller\API\v1\Pub\Mall\MallListAPIController;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Orbit\Helper\Util\SimpleCache;
use \DB;
use VendorGTMCity;
use VendorGTMCountry;
use \stdClass;
use \Activity;

class LocationDetectionAPIController extends PubControllerAPI
{
    public function getCountryAndCity()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;
        $mall = null;

        try {

            $this->checkAuth();
            $user = $this->api->user;

            $mallId = OrbitInput::get('mall_id', null);
            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $userLocation = OrbitInput::get('ul', null);

            if (! empty($userLocation)) {
                $data = $this->getLocationGPSEnabled($userLocation);
            } else {
                $data = $this->getLocationGPSDisabled();
            }

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';

            $cities = implode(',', $data->cities);

            $activityNotes = sprintf($cities);
            $activity->setUser($user)
                ->setActivityName('detect_location')
                ->setActivityNameLong('Detect Location')
                ->setObject(null)
                ->setObjectName($data->country)
                ->setLocation($mall)
                ->setModuleName('Location Auto Detection')
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();

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

    public function getLocationGPSEnabled($userLocation)
    {
        $country = null;
        $cities = [];

        if (! empty($userLocation)) {
            $position = explode("|", $userLocation);
            $lon = $position[0];
            $lat = $position[1];
        } else {
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            // get lon lat from cookie
            $userLocationCookieArray = isset($_COOKIE[$userLocationCookieName]) ? explode('|', $_COOKIE[$userLocationCookieName]) : NULL;
            if (! is_null($userLocationCookieArray) && isset($userLocationCookieArray[0]) && isset($userLocationCookieArray[1])) {
                $lon = $userLocationCookieArray[0];
                $lat = $userLocationCookieArray[1];
            }
        }

        // validate longitude value
        if (! preg_match('/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/', $lon)) {
            OrbitShopAPI::throwInvalidArgument('The longitude value is not valid');
        }

        // validate latitude value
        if (! preg_match('/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/', $lat)) {
            OrbitShopAPI::throwInvalidArgument('The latitude value is not valid');
        }

        // get 1 nearest mall and set the country and cities based on that mall
        $_GET['ul'] = $userLocation;
        $_GET['skip'] = '0';
        $_GET['sortby'] = 'location';
        $_GET['sortmode'] = 'asc';
        $_GET['take'] = '1';
        $response = MallListAPIController::create('raw')->getMallList();
        if (is_object($response) && $response->code === 0) {
            if (! empty($response->data->returned_records)) {
                $country = $response->data->records[0]['country'];
                $cities[0] = $response->data->records[0]['city'];
            }
        }

        return $this->dataFormatter($country, $cities);
    }

    public function getLocationGPSDisabled()
    {
        $country = null;
        $cities = [];
        $cacheKey = [];
        $serializedCacheKey = [];

        // Cache result of all possible calls to backend storage
        $cacheConfig = Config::get('orbit.cache.context');
        $cacheContext = 'location-detection';
        $recordCache = SimpleCache::create($cacheConfig, $cacheContext);

        // get the client IP
        $clientIpAddress = $_SERVER['REMOTE_ADDR'];

        // override ip address for testing purpose
        if (Config::get('app.debug')) {
            $clientIpAddress = isset($_COOKIE['USER_IP_ADDRESS']) ? $_COOKIE['USER_IP_ADDRESS'] : $clientIpAddress;
        }

        // set cache key for this IP Address
        $cacheKey = ['ip_address' => $clientIpAddress];

        // get record from DBIP
        $addr_type = "ipv4";
        if (ip2long($clientIpAddress) !== false) {
            $addr_type = "ipv4";
        } else if (preg_match('/^[0-9a-fA-F:]+$/', $clientIpAddress) && @inet_pton($clientIpAddress)) {
            $addr_type = "ipv6";
        }

        // serialize cache key
        $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);

        // get the response from cache and fallback to query
        $response = $recordCache->get($serializedCacheKey, function() use ($clientIpAddress, $addr_type) {
            $country = null;
            $cities = [];
            $dbip = DB::connection(Config::get('orbit.dbip.connection_id'))
                ->table(Config::get('orbit.dbip.table'))
                ->where('ip_start', '<=', inet_pton($clientIpAddress))
                ->where('addr_type', '=', $addr_type)
                ->orderBy('ip_start', 'desc')
                ->first();

            if (is_object($dbip)) {
                // get GTM country mapping
                $gtmCountry = VendorGTMCountry::where('vendor_country', $dbip->country)
                    ->first();

                $gtmCities = VendorGTMCity::leftJoin('vendor_gtm_countries', 'vendor_gtm_countries.vendor_country', '=', 'vendor_gtm_cities.vendor_country')
                    ->where('vendor_gtm_cities.vendor_country', $dbip->country)
                    ->where('vendor_city', $dbip->city)
                    ->get();

                if (is_object($gtmCountry)) {
                    $country = $gtmCountry->gtm_country;
                }

                if ($gtmCities->count() > 0) {
                    foreach ($gtmCities as $gtmCity) {
                        $cities[] = $gtmCity->gtm_city;
                    }
                }
            }

            $locationData = new stdClass();
            $locationData->country = $country;
            $locationData->cities = $cities;

            return $locationData;
        });
        $recordCache->put($serializedCacheKey, $response);

        if (is_object($response)) {
            $country = $response->country;
            $cities = $response->cities;
        }

        return $this->dataFormatter($country, $cities);
    }

    /**
     * Format the data before adding it to response
     */
    public function dataFormatter($country = null, $cities = [])
    {
        $data = new stdClass();
        $data->country = $country;
        $data->cities = $cities;

        return $data;
    }
}
