<?php
/**
 * An API controller for managing DB-IP.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;


class DBIPAPIController extends ControllerAPI
{
    public function getSearchDBIPCountry()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.mall.getsearchdbipcountry.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.mall.getsearchdbipcountry.after.auth', array($this));

            // Try to check access control list, does this mall allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.mall.getsearchdbipcountry.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.mall.getsearchdbipcountry.after.authz', array($this, $user));

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:db_ip_country_id, country',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.dbip_country_sortby'),
                )
            );

            Event::fire('orbit.mall.getsearchdbipcountry.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.mall.getsearchdbipcountry.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.dbip_country.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.dbip_country.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $prefix = DB::getTablePrefix();

            $dbIpCountry = DBIPCountry::select('db_ip_country_id', 'country');

            // Filter DB IP Country by db_ip_country_id
            OrbitInput::get('db_ip_country_id', function ($dbIpCountryIds) use ($dbIpCountry) {
                $dbIpCountry->whereIn('db_ip_countries.db_ip_country_id', $dbIpCountryIds);
            });

            // Filter DB IP Country by country_like
            OrbitInput::get('country_like', function ($country_like) use ($dbIpCountry) {
                $dbIpCountry->where('country', 'like', "%$country_like%");
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_dbIpCountry = clone $dbIpCountry;

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
            $dbIpCountry->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $dbIpCountry->skip($skip);

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
            $dbIpCountry->orderBy($sortBy, $sortMode);

            $totalCountry = RecordCounter::create($_dbIpCountry)->count();
            $listCountry = $dbIpCountry->get();

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
            Event::fire('orbit.country.getsearchcountry.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.country.getsearchcountry.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.country.getsearchcountry.query.error', array($this, $e));

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
            Event::fire('orbit.country.getsearchcountry.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.country.getsearchcountry.before.render', array($this, &$output));

        return $output;
    }

    public function getSearchDBIPCity()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.mall.getsearchdbipcity.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.mall.getsearchdbipcity.after.auth', array($this));

            // Try to check access control list, does this mall allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.mall.getsearchdbipcity.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.mall.getsearchdbipcity.after.authz', array($this, $user));

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:db_ip_city_id, country', 'city',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.dbip_city_sortby'),
                )
            );

            Event::fire('orbit.mall.getsearchdbipcity.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.mall.getsearchdbipcity.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.dbip_city.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.dbip_city.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $prefix = DB::getTablePrefix();
            $vendor = Config::get('orbit.vendor_ip_database.default', 'dbip');

            $ipCity = DBIPCity::select('db_ip_city_id', 'country', 'city');
            if ($vendor === 'ip2location') {
                $ipCity = Ip2Location::select('ip2location_city_id as db_ip_city_id', 'country', 'city');
            }

            // Filter DB IP Country by country_like
            OrbitInput::get('country', function ($country_like) use ($ipCity) {
                $ipCity->where('country', $country_like);
            });

            // Filter DB IP Country by city_like
            OrbitInput::get('city_like', function ($city_like) use ($ipCity) {
                $ipCity->where('city', 'like', "%$city_like%");
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_ipCity = clone $ipCity;

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
            $ipCity->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $ipCity->skip($skip);

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
            $ipCity->orderBy($sortBy, $sortMode);

            $totalCountry = RecordCounter::create($_ipCity)->count();
            $listCountry = $ipCity->get();

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
            Event::fire('orbit.city.getsearchcity.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.city.getsearchcity.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.city.getsearchcity.query.error', array($this, $e));

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
            Event::fire('orbit.city.getsearchcity.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.city.getsearchcity.before.render', array($this, &$output));

        return $output;
    }
}