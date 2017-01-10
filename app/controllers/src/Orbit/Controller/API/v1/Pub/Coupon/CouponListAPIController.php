<?php namespace Orbit\Controller\API\v1\Pub\Coupon;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use Coupon;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Activity;
use Mall;
use Advert;
use Lang;
use Role;
use IssuedCoupon;
use \Exception;
use Orbit\Controller\API\v1\Pub\Coupon\CouponHelper;
use Orbit\Helper\Util\GTMSearchRecorder;
use Orbit\Helper\Util\ObjectPartnerBuilder;
use Orbit\Helper\Database\Cache as OrbitDBCache;
use Helper\EloquentRecordCounter as RecordCounter;
use \Carbon\Carbon as Carbon;
use Orbit\Helper\Util\SimpleCache;
use Elasticsearch\ClientBuilder;
use PartnerAffectedGroup;

class CouponListAPIController extends PubControllerAPI
{
    /**
     * GET - get all coupon in all mall
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
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
    public function getCouponList()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $mall = NULL;
        $user = NULL;
        $httpCode = 200;
        $cacheKey = [];
        $serializedCacheKey = [];

        // Cache result of all possible calls to backend storage
        $cacheConfig = Config::get('orbit.cache.context');
        $cacheContext = 'coupon-list';
        $recordCache = SimpleCache::create($cacheConfig, $cacheContext);
        $featuredRecordCache = SimpleCache::create($cacheConfig, $cacheContext)
                                          ->setKeyPrefix($cacheContext . '-featured');
        $totalRecordCache = SimpleCache::create($cacheConfig, $cacheContext)
                                       ->setKeyPrefix($cacheContext . '-total-rec');

        try {
            $this->checkAuth();
            $user = $this->api->user;
            $host = Config::get('orbit.elasticsearch');
            $sort_by = OrbitInput::get('sortby', 'created_date');
            $sort_mode = OrbitInput::get('sortmode','desc');
            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $location = OrbitInput::get('location', null);
            $ul = OrbitInput::get('ul', null);
            $language = OrbitInput::get('language', 'id');
            $userLocationCookieName = Config::get('orbit.user_location.cookie.name');
            $distance = Config::get('orbit.geo_location.distance', 10);
            $lon = '';
            $lat = '';
            $mallId = OrbitInput::get('mall_id', null);
            $withPremium = OrbitInput::get('is_premium', null);
            $list_type = OrbitInput::get('list_type', 'featured');
            $from_mall_ci = OrbitInput::get('from_mall_ci', null);
            $category_id = OrbitInput::get('category_id');
            $no_total_records = OrbitInput::get('no_total_records', null);
            $take = PaginationNumber::parseTakeFromGet('coupon');
            $skip = PaginationNumber::parseSkipFromGet();

            $couponHelper = CouponHelper::create();
            $couponHelper->couponCustomValidator();
            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $validator = Validator::make(
                array(
                    'language' => $language,
                    'sortby'   => $sort_by,
                    'list_type'   => $list_type,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,location,created_date',
                    'list_type'   => 'in:featured,preferred',
                ),
                array(
                )
            );

            // Pass all possible parameters to be used as cache key.
            // Make sure there is no missing one.
            $cacheKey = [
                'sort_by' => $sort_by, 'sort_mode' => $sort_mode, 'language' => $language,
                'location' => $location, 'ul' => $ul,
                'user_location_cookie_name' => isset($_COOKIE[$userLocationCookieName]) ? $_COOKIE[$userLocationCookieName] : NULL,
                'distance' => $distance, 'mall_id' => $mallId,
                'with_premium' => $withPremium, 'list_type' => $list_type,
                'from_mall_ci' => $from_mall_ci, 'category_id' => $category_id,
                'no_total_record' => $no_total_records,
                'take' => $take, 'skip' => $skip,
            ];

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $couponHelper->getValidLanguage();

            $prefix = DB::getTablePrefix();

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            //Get now time, time must be 2017-01-09T15:30:00Z
            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateTime = $date->setTimezone('Asia/Jakarta')->toDateTimeString();
            $dateTime = explode(' ', $dateTime);
            $dateTimeEs = $dateTime[0] . 'T' . $dateTime[1] . 'Z';

            $withScore = false;
            $esTake = $take;
            if ($list_type === 'featured') {
                $esTake = 50;
            }
            $jsonArea = array("from" => $skip, "size" => $esTake, "query" => array("filtered" => array("filter" => array("and" => array( array("query" => array("match" => array("status" => "active"))), array("range" => array("begin_date" => array("lte" => $dateTimeEs))), array("range" => array("end_date" => array("gte" => $dateTimeEs))))))));

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

            $withKeywordSearch = false;
            OrbitInput::get('keyword', function($keyword) use (&$jsonArea, &$searchFlag, &$withScore, &$withKeywordSearch, &$cacheKey)
            {
                $cacheKey['keyword'] = $keyword;
                if ($keyword != '') {
                    $searchFlag = $searchFlag || TRUE;
                    $withScore = true;
                    $withKeywordSearch = true;

                    $filterTranslation = array("nested" => array("path" => "translation", "query" => array("multi_match" => array("query" => $keyword, "fields" => array("translation.name^6", "translation.description^3")))));
                    $jsonArea['query']['filtered']['query']['bool']['should'][] = $filterTranslation;

                    $filterTenant = array("nested" => array("path" => "link_to_tenant", "query" => array("multi_match" => array("query" => $keyword, "fields" => array("link_to_tenant.city^2", "link_to_tenant.province^2", "link_to_tenant.country^1")))));
                    $jsonArea['query']['filtered']['query']['bool']['should'][] = $filterTenant;

                    $filterKeyword = array("multi_match" => array("query" => $keyword, "fields" => array("object_type^5", "keywords^4")));
                    $jsonArea['query']['filtered']['query']['bool']['should'][] = $filterKeyword;
                }
            });

            OrbitInput::get('mall_id', function($mallId) use (&$jsonArea) {
                if (! empty($mallId)) {
                    $withMallId = array("nested" => array("path" => "link_to_tenant", "query" => array("filtered" => array("filter" => array("match" => array("link_to_tenant.parent_id" => $mallId))))));
                    $jsonArea['query']['filtered']['filter']['and'][] = $withMallId;
                }
             });

            // filter by category_id
            OrbitInput::get('category_id', function($categoryIds) use (&$jsonArea, &$searchFlag) {
                $searchFlag = $searchFlag || TRUE;
                if (! is_array($categoryIds)) {
                    $categoryIds = (array)$categoryIds;
                }

                foreach ($categoryIds as $key => $value) {
                    $categoryFilter["or"][] = array("match" => array("category_ids" => $value));
                }
                $jsonArea['query']['filtered']['filter']['and'][] = $categoryFilter;
            });

            OrbitInput::get('partner_id', function($partnerId) use (&$jsonArea, $prefix, &$searchFlag, &$cacheKey) {
                $cacheKey['partner_id'] = $partnerId;
                $partnerFilter = '';
                if (! empty($partnerId)) {
                    $searchFlag = $searchFlag || TRUE;
                    $partnerAffected = PartnerAffectedGroup::join('affected_group_names', function($join) {
                                                                $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                                                                     ->where('affected_group_names.group_type', '=', 'coupons');
                                                            })
                                                            ->where('partner_id', $partnerId)
                                                            ->first();

                    if (is_object($partnerAffected)) {
                        $exception = Config::get('orbit.partner.exception_behaviour.partner_ids', []);
                        $partnerFilter = array("query" => array("match" => array("partner_ids" => $partnerId)));

                        if (in_array($partnerId, $exception)) {
                            $partnerException = PartnerCompetitor::where("partner_id", $partnerId)->lists("competitor_id");
                            $partnerIds = implode('", "', $partnerException);
                            $partnerFilter = array("query" => array("not" => array("terms" => array("partner_ids" => $partnerIds))));
                        }
                        $jsonArea['query']['filtered']['filter']['and'][] = $partnerFilter;
                    }
                }
            });

            // filter by location (city or user location)
            OrbitInput::get('location', function($location) use (&$jsonArea, &$searchFlag, &$withScore, $lat, $lon, $distance)
            {
                if (! empty($location)) {
                    $searchFlag = $searchFlag || TRUE;

                    if ($location === "mylocation" && $lat != '' && $lon != '') {
                        $locationFilter = array("nested" => array("path" => "link_to_tenant", "query" => array("filtered" => array("filter" => array("geo_distance" => array("distance" => $distance."km", "link_to_tenant.position" => array("lon" => $lon, "lat" => $lat)))))));
                        $jsonArea['query']['filtered']['filter']['and'][] = $locationFilter;
                    } elseif ($location !== "mylocation") {
                        $locationFilter = array("nested" => array("path" => "link_to_tenant", "query" => array("filtered" => array("filter" => array("match" => array("link_to_tenant.city.raw" => $location))))));
                        $jsonArea['query']['filtered']['filter']['and'][] = $locationFilter;
                    }
                }
            });

            // sort by name or location
            if ($sort_by === 'location' && $lat != '' && $lon != '') {
                $searchFlag = $searchFlag || TRUE;
                $sort = array("_geo_distance" => array("link_to_tenant.position" => array("lon" => $lon, "lat" => $lat), "order" => $sort_mode, "unit" => "km", "distance_type" => "plane"));
            } elseif ($sort_by === 'created_date') {
                $sort = array("begin_date" => array("order" => $sort_mode));
            } else {
                $sort = array("name.raw" => array("order" => $sort_mode));
            }

            $sortby = $sort;
            if ($withScore) {
                $sortby = array("_score", $sort);
            }
            $jsonArea["sort"] = $sortby;

            $advert_location_type = 'gtm';
            $advert_location_id = '0';
            if (! empty($mallId)) {
                $advert_location_type = 'mall';
                $advert_location_id = $mallId;
            }

            $adverts = Advert::select('adverts.advert_id',
                                    'adverts.link_object_id',
                                    'advert_placements.placement_type',
                                    'advert_placements.placement_order',
                                    'media.path',
                                    DB::raw("CASE WHEN placement_type = 'featured_list' THEN 0 ELSE 1 END AS with_preferred"))
                            ->join('advert_link_types', function ($q) {
                                $q->on('advert_link_types.advert_link_type_id', '=', 'adverts.advert_link_type_id');
                                $q->on('advert_link_types.advert_link_name', '=', DB::raw("'Coupon'"));
                            })
                            ->join('advert_locations', function ($q) use ($advert_location_id, $advert_location_type) {
                                $q->on('advert_locations.advert_id', '=', 'adverts.advert_id');
                                $q->on('advert_locations.location_id', '=', DB::raw("'" . $advert_location_id . "'"));
                                $q->on('advert_locations.location_type', '=', DB::raw("'" . $advert_location_type . "'"));
                            })
                            ->join('advert_placements', function ($q) use ($list_type) {
                                $q->on('advert_placements.advert_placement_id', '=', 'adverts.advert_placement_id');
                                if ($list_type === 'featured') {
                                    $q->on('advert_placements.placement_type', 'in', DB::raw("('featured_list', 'preferred_list_regular', 'preferred_list_large')"));
                                } else {
                                    $q->on('advert_placements.placement_type', 'in', DB::raw("('preferred_list_regular', 'preferred_list_large')"));
                                }
                            })
                            ->leftJoin('media', function ($q) {
                                $q->on("object_id", '=', "adverts.advert_id");
                                $q->on("media_name_long", '=', DB::raw("'advert_image_orig'"));
                            })
                            ->where('adverts.status', '=', DB::raw("'active'"))
                            ->where('adverts.start_date', '<=', DB::raw("CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$timezone}')"))
                            ->where('adverts.end_date', '>=', DB::raw("CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$timezone}')"))
                            ->orderBy('advert_placements.placement_order', 'desc');

            $advertData = DB::table(DB::raw("({$adverts->toSql()}) as adv"))
                         ->mergeBindings($adverts->getQuery())
                         ->select(DB::raw("adv.advert_id,
                                    adv.link_object_id,
                                    adv.placement_order, 
                                    adv.path,
                                    adv.placement_type as placement_type_orig,
                                    CASE WHEN SUM(with_preferred) > 0 THEN 'preferred_list_large' ELSE placement_type END AS placement_type"))
                         ->groupBy(DB::raw("adv.link_object_id"))
                         ->take(100)
                         ->get();

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $_jsonArea = $jsonArea;

            if ($withKeywordSearch) {
                // if user searching, we call es twice, first for get coupon data that match with keyword and then get the id,
                // and second, call es data combine with advert
                unset($jsonArea['query']['filtered']['query']);

                $_paramArea = [
                    'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.coupons.index'),
                    'type'   => Config::get('orbit.elasticsearch.indices.coupons.type'),
                    'body' => json_encode($_jsonArea)
                ];
                $searchResponse = $client->search($_paramArea);

                $searchData = $searchResponse['hits'];

                $couponIds = array();
                foreach ($searchData['hits'] as $content) {
                    foreach ($content as $key => $val) {
                        if ($key === "_id") {
                            $couponIds[] = $val;
                        }
                    }
                }
                $jsonArea['query']['filtered']['filter']['and'][] = array("terms" => array("_id" => $couponIds));
            }

            // call es
            if (! empty($advertData)) {
                unset($jsonArea["sort"]);
                $withScore = true;
                foreach ($advertData as $dt) {
                    $esAdvert = array("match" => array("_id" => array("query" => $dt->link_object_id, "boost" => $dt->placement_order)));
                    $jsonArea['query']['filtered']['query']['bool']['should'][] = $esAdvert;
                }
                $jsonArea['query']['filtered']['query']['bool']['should'][] = array("match_all" => new stdclass());
            }

            $sortby = $sort;
            if ($withScore) {
                $sortby = array("_score", $sort);
            }
            $jsonArea["sort"] = $sortby;

            $paramArea = [
                'index'  => $esPrefix . Config::get('orbit.elasticsearch.indices.coupons.index'),
                'type'   => Config::get('orbit.elasticsearch.indices.coupons.type'),
                'body' => json_encode($jsonArea)
            ];

            $userRole = Role::select('role_name')->where('role_id', $user->user_role_id)->first();
            if ($userRole->role_name !== "Guest") {
                $myCoupons = IssuedCoupon::select('issued_coupon_id')
                                ->where('issued_coupons.user_id', '=', $this->quote($user->user_id))
                                ->where('issued_coupons.status', '=', 'issued')
                                ->orderBy('created_at', 'desc')
                                ->take(100)
                                ->get();
            }

            $serializedCacheKey = SimpleCache::transformDataToHash($cacheKey);
            $response = $recordCache->get($serializedCacheKey, function() use ($client, &$paramArea) {
                return $client->search($paramArea);
            });
            $recordCache->put($serializedCacheKey, $response);

            $records = $response['hits'];

            $listOfRec = array();
            foreach ($records['hits'] as $record) {
                $data = array();
                $isOwned = false;
                foreach ($record['_source'] as $key => $value) {
                    if ($key === "name") {
                        $key = "coupon_name";
                    } elseif ($key === "promotion_id") {
                        $key = "coupon_id";
                    }
                    $data[$key] = $value;

                    // translation, to get name, desc and image
                    if ($key === "translation") {
                        foreach ($record['_source']['translation'] as $dt) {
                            if ($dt['language_id'] === $valid_language->language_id) {
                                // name & desc
                                if (! empty($dt['name'])) {
                                    $data['coupon_name'] = $dt['name'];
                                    $data['description'] = $dt['description'];
                                }

                                // image
                                if (! empty($dt['image_url'])) {
                                    $data['image_url'] = $dt['image_url'];
                                }
                            } else {
                                // name & desc
                                if (! empty($dt['name']) && empty($data['coupon_name'])) {
                                    $data['coupon_name'] = $dt['name'];
                                    $data['description'] = $dt['description'];
                                }

                                // image
                                if (! empty($dt['image_url']) && empty($data['image_url'])) {
                                    $data['image_url'] = $dt['image_url'];
                                }
                            }
                        }
                    }

                    // advert
                    if ($key === "coupon_id") {
                        $data['placement_type'] = null;
                        $data['placement_type_orig'] = null;
                        foreach ($advertData as $advData) {

                            if ($advData->link_object_id === $value) {
                                $data['placement_type'] = $advData->placement_type;
                                $data['placement_type_orig'] = $advData->placement_type_orig;

                                // image
                                if (! empty($advData->path)) {
                                    $data['image_url'] = $advData->path;
                                }
                                break;
                            }
                        }
                    }

                    if ($userRole->role_name !== "Guest") {
                        foreach ($myCoupons as $myCoupon) {

                            if ($myCoupon->promotion_id === $value) {
                                $isOwned = true;
                                break;
                            }
                        }
                    }
                }
                $data['owned'] = $isOwned;
                $data['score'] = $record['_score'];
                $listOfRec[] = $data;
            }

            // record GTM search activity
            if ($searchFlag) {
                $parameters = [
                    'displayName' => 'Coupon',
                    'keywords' => OrbitInput::get('keyword', NULL),
                    'categories' => OrbitInput::get('category_id', NULL),
                    'location' => OrbitInput::get('location', NULL),
                    'sortBy' => OrbitInput::get('sortby', 'name'),
                    'partner' => OrbitInput::get('partner_id', NULL)
                ];

                GTMSearchRecorder::create($parameters)->saveActivity($user);
            }

            // frontend need the mall name
            $mall = null;
            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = count($listOfRec);
            if (is_object($mall)) {
                $data->mall_name = $mall->name;
            }
            $data->records = $listOfRec;

            // // random featured adv
            // // @todo fix for random -- this is not the right way to do random, it could lead to memory leak
            if ($list_type === 'featured') {
                $advertedCampaigns = array_filter($listOfRec, function($v) {
                    return ($v['placement_type_orig'] === 'featured_list');
                });

                if (count($advertedCampaigns) > $take) {
                    $output = array();
                    $listSlide = array_rand($advertedCampaigns, $take);
                    if (count($listSlide) > 1) {
                        foreach ($listSlide as $key => $value) {
                            $output[] = $advertedCampaigns[$value];
                        }
                    } else {
                        $output = $advertedCampaigns[$listSlide];
                    }
                } else {
                    $output = array_slice($listOfRec, 0, 4);
                }

                $data->returned_records = count($listOfRec);
                $data->total_records = count($output);
                $data->records = $output;
            }

            // save activity when accessing listing
            // omit save activity if accessed from mall ci campaign list 'from_mall_ci' !== 'y'
            // moved from generic activity number 32
            if (OrbitInput::get('from_homepage', '') !== 'y') {
                if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                    if (is_object($mall)) {
                        $activityNotes = sprintf('Page viewed: View mall coupon list');
                        $activity->setUser($user)
                            ->setActivityName('view_mall_coupon_list')
                            ->setActivityNameLong('View mall coupon list')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Coupon')
                            ->setNotes($activityNotes)
                            ->responseOK()
                            ->save();
                    } else {
                        $activityNotes = sprintf('Page viewed: Coupon list');
                        $activity->setUser($user)
                            ->setActivityName('view_coupons_main_page')
                            ->setActivityNameLong('View Coupons Main Page')
                            ->setObject(null)
                            ->setLocation($mall)
                            ->setModuleName('Coupon')
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

        $output = $this->render($httpCode);

        return $output;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    protected function getCouponData() {

        $jsonArea = array("from" => $skip, "size" => $take, "query" => array("filtered" => array("filter" => array("and" => array( array("query" => array("match" => array("status" => "active"))), array("range" => array("begin_date" => array("lte" => "now", "time_zone" => "+07:00"))), array("range" => array("end_date" => array("gte" => "now", "time_zone" => "+07:00"))))))));
    }
}
