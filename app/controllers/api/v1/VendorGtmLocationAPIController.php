<?php
/**
 * An API controller for managing Vendor Gtm City and Country.
 */

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;


class VendorGtmLocationAPIController extends ControllerAPI
{
    public function getSearchVendorGtmCountry()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcountry.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcountry.after.auth', array($this));

            // Try to check access control list, does this mall allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcountry.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcountry.after.authz', array($this, $user));

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:vendor_country',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.dbip_country_sortby'),
                )
            );

            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcountry.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcountry.after.validation', array($this, $validator));

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

            $vendorGtmCountry = VendorGTMCountry::select('vendor_country');

            // Filter DB IP Country by country
            OrbitInput::get('country', function ($country) use ($vendorGtmCountry) {
                $vendorGtmCountry->where('gtm_country', $country);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_vendorGtmCountry = clone $vendorGtmCountry;

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
            $vendorGtmCountry->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $vendorGtmCountry->skip($skip);

            // Default sort by
            $sortBy = 'vendor_country';
            // Default sort mode
            $sortMode = 'asc';
            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'vendor_country' => 'vendor_country'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $vendorGtmCountry->orderBy($sortBy, $sortMode);

            $totalCountry = RecordCounter::create($_vendorGtmCountry)->count();
            $listCountry = $vendorGtmCountry->get();

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
            Event::fire('orbit.country.getsearchvendorgtmcountry.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.country.getsearchvendorgtmcountry.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.country.getsearchvendorgtmcountry.query.error', array($this, $e));

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
            Event::fire('orbit.country.getsearchvendorgtmcountry.general.exception', array($this, $e));

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
        Event::fire('orbit.country.getsearchvendorgtmcountry.before.render', array($this, &$output));

        return $output;
    }

    public function getSearchVendorGtmCity()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcity.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcity.after.auth', array($this));

            // Try to check access control list, does this mall allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcity.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcity.after.authz', array($this, $user));

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:vendor_city, gtm_city',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.dbip_city_sortby'),
                )
            );

            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcity.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcity.after.validation', array($this, $validator));

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

            $vendor_ip_database = Config::get('orbit.vendor_ip_database.default', 'dbip');
            $vendorGtmCity = VendorGTMCity::select('vendor_city')->where('vendor_type', $vendor_ip_database);

            // Filter vendor gtm
            OrbitInput::get('gtm_city', function ($gtm_city) use ($vendorGtmCity) {
                $vendorGtmCity->where('gtm_city', $gtm_city);
            });

            OrbitInput::get('country_id', function ($country_id) use ($vendorGtmCity) {
                $vendorGtmCity->where('country_id', $country_id);
            });

            OrbitInput::get('vendor_country', function ($vendor_country) use ($vendorGtmCity) {
                $vendorGtmCity->where('vendor_country', $vendor_country);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_vendorGtmCity = clone $vendorGtmCity;

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
            $vendorGtmCity->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $vendorGtmCity->skip($skip);

            // Default sort by
            $sortBy = 'vendor_city';
            // Default sort mode
            $sortMode = 'asc';
            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'vendor_city' => 'vendor_city'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $vendorGtmCity->orderBy($sortBy, $sortMode);

            $totalCountry = RecordCounter::create($_vendorGtmCity)->count();
            $listCountry = $vendorGtmCity->get();

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
            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcity.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcity.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcity.query.error', array($this, $e));

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
            Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcity.general.exception', array($this, $e));

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
        Event::fire('orbit.vendorgtmlocation.getsearchvendorgtmcity.before.render', array($this, &$output));

        return $output;
    }
}