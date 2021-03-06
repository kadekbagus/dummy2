<?php
/**
 * An API controller for mall location (country,city,etc).
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use DominoPOS\OrbitUploader\Uploader as OrbitUploader;

class MallLocationAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    /**
     * GET - Mall Country List
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string            `mall_country_id`               (optional) - mall country id
     * @param string            `country_id`                    (optional) - country id
     * @param string            `country_like`                  (optional) - country
     * @param string            `sort_by`                       (optional) - column order by
     * @param string            `sort_mode`                     (optional) - asc or desc
     * @param integer           `take`                          (optional) - limit
     * @param integer           `skip`                          (optional) - limit
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function getSearchMallCountry()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.mall.getsearchmallcountry.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.mall.getsearchmallcountry.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.mall.getsearchmallcountry.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.mall.getsearchmallcountry.after.authz', array($this, $user));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.mall_country.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.mall_country.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $mallCountry = MallCountry::select('mall_country_id', 'country_id', 'country');

            // Filter Mall Country by mall_country_id
            OrbitInput::get('mall_country_id', function ($mallCountryId) use ($mallCountry) {
                $mallCountryId = (array)$mallCountryId;
                $mallCountry->whereIn('mall_country_id', $mallCountryId);
            });

            // Filter Mall Country by country_id
            OrbitInput::get('country_id', function ($CountryId) use ($mallCountry) {
                $CountryId = (array)$CountryId;
                $mallCountry->whereIn('country_id', $CountryId);
            });

            // Filter Mall Country by country_like
            OrbitInput::get('country_like', function ($countryLike) use ($mallCountry) {
                $mallCountry->where('country', 'like', "%$countryLike%");
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_mallCountry = clone $mallCountry;

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $mallCountry->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $mallCountry->skip($skip);

            // Default sort by
            $sortBy = 'country';
            // Default sort mode
            $sortMode = 'asc';
            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'country' => 'country'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $mallCountry->orderBy($sortBy, $sortMode);

            $totalCountry = RecordCounter::create($_mallCountry)->count();
            $listCountry = $mallCountry->get();

            $data = new stdclass();
            $data->total_records = $totalCountry;
            $data->returned_records = count($listCountry);
            $data->records = $listCountry;

            if ($totalCountry === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.country');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.query.error', array($this, $e));

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
            Event::fire('orbit.mall.getsearchmallcountry.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.mall.getsearchmallcountry.before.render', array($this, &$output));

        return $output;
    }


    /**
     * GET - Mall City List
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string            `mall_city_id`               (optional) - mall city id
     * @param string            `country_id`                 (optional) - country id
     * @param string            `city_like`                  (optional) - city
     * @param string            `sort_by`                    (optional) - column order by
     * @param string            `sort_mode`                  (optional) - asc or desc
     * @param integer           `take`                       (optional) - limit
     * @param integer           `skip`                       (optional) - limit
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function getSearchMallCity()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.mall.getsearchmallcity.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.mall.getsearchmallcity.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.mall.getsearchmallcity.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.mall.getsearchmallcity.after.authz', array($this, $user));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.mall_city.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.mall_city.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $prefix = DB::getTablePrefix();
            $mallCity = MallCity::select('mall_city_id', 'city', 'country_id');

            OrbitInput::get('campaign_id', function ($campaign_id) use ($mallCity, $prefix) {
                OrbitInput::get('link_type', function ($link_type) use ($mallCity, $campaign_id, $prefix) {
                    // get city based on link to tenant
                    $tenants = null;
                    switch($link_type) {
                        case 'promotion':
                                $tenants = NewsMerchant::select(DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.merchant_id, {$prefix}merchants.merchant_id) as mall_id"),
                                                                DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.name, `{$prefix}merchants`.`name`) AS display_name"),
                                                                DB::raw("pm.city"))
                                                        ->leftjoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                                        ->leftjoin('merchants as pm', DB::raw("pm.merchant_id"), '=', DB::raw("IF(isnull(`{$prefix}merchants`.`parent_id`), `{$prefix}merchants`.`merchant_id`, `{$prefix}merchants`.`parent_id`) "))
                                                        ->where('news_id', $campaign_id)
                                                        ->groupBy('mall_id')
                                                        ->get();
                                break;
                        case 'news':
                                $tenants = NewsMerchant::select(DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.merchant_id, {$prefix}merchants.merchant_id) as mall_id"),
                                                                DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.name, `{$prefix}merchants`.`name`) AS display_name"),
                                                                DB::raw("pm.city"))
                                                        ->leftjoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                                        ->leftjoin('merchants as pm', DB::raw("pm.merchant_id"), '=', DB::raw("IF(isnull(`{$prefix}merchants`.`parent_id`), `{$prefix}merchants`.`merchant_id`, `{$prefix}merchants`.`parent_id`) "))
                                                        ->where('news_id', $campaign_id)
                                                        ->groupBy('mall_id')
                                                        ->get();
                                break;
                        case 'coupon':
                                $tenants = PromotionRetailer::select(DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.merchant_id, {$prefix}merchants.merchant_id) as mall_id"),
                                                                     DB::raw("IF({$prefix}merchants.object_type = 'tenant', pm.name, `{$prefix}merchants`.`name`) AS display_name"),
                                                                     DB::raw("pm.city"))
                                                            ->leftjoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                                            ->leftjoin('merchants as pm', DB::raw("pm.merchant_id"), '=', DB::raw("IF(isnull(`{$prefix}merchants`.`parent_id`), `{$prefix}merchants`.`merchant_id`, `{$prefix}merchants`.`parent_id`) "))
                                                            ->where('promotion_id', $campaign_id)
                                                            ->groupBy('mall_id')
                                                            ->get();
                                break;
                    }

                    $arrCity = [];
                    if (!empty($tenants)) {
                        foreach($tenants as $key => $value) {
                            if (isset($tenants[$key]->city)) {
                                $arrCity[] = $tenants[$key]->city;
                            }
                        }
                    }

                    if (!empty($arrCity)) {
                        $mallCity->whereIn('city', $arrCity);
                    }
                });
            });

            // Filter Mall City by mall_city_id
            OrbitInput::get('mall_city_id', function ($mallCityId) use ($mallCity) {
                $mallCityId = (array)$mallCityId;
                $mallCity->whereIn('mall_city_id', $mallCityId);
            });

            // Filter Mall City by city_like
            OrbitInput::get('city_like', function ($cityLike) use ($mallCity) {
                $mallCity->where('city', 'like', "%$cityLike%");
            });

            // Filter Mall City by country_id
            OrbitInput::get('country_id', function ($country_id) use ($mallCity) {
                $mallCity->where('country_id', '=', $country_id);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_mallCity= clone $mallCity;

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $mallCity->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $mallCity->skip($skip);

            // Default sort by
            $sortBy = 'city';
            // Default sort mode
            $sortMode = 'asc';
            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'city' => 'city'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $mallCity->orderBy($sortBy, $sortMode);

            $totalCity = RecordCounter::create($_mallCity)->count();
            $listCity = $mallCity->get();

            $data = new stdclass();
            $data->total_records = $totalCity;
            $data->returned_records = count($listCity);
            $data->records = $listCity;

            if ($totalCity === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.country');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mall.getsearchmallcity.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mall.getsearchmallcity.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.mall.getsearchmallcity.query.error', array($this, $e));

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
            Event::fire('orbit.mall.getsearchmallcity.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.mall.getsearchmallcity.before.render', array($this, &$output));

        return $output;
    }
}