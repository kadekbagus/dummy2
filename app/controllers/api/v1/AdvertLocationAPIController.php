<?php
/**
 * An API controller for displaying Advert Locations.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class AdvertLocationAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'mall customer service', 'campaign admin'];

    /**
     * GET - Get Mall (Locations) based on Advert
     *
     * @author kadek<kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string `advert_id (required) - Advert id
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getAdvertLocations()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.advertlocations.getadvertlocations.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.advertlocations.getadvertlocations.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.advertlocations.getadvertlocations.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.advertlocations.getadvertlocations.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $advert_id = OrbitInput::get('advert_id');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'advert_id' => $advert_id,
                ),
                array(
                    'advert_id' => 'required',
                )
            );

            Event::fire('orbit.advertlocations.getadvertlocations.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.advertlocations.getadvertlocations.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.coupon.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }

            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.coupon.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $prefix = DB::getTablePrefix();

            $advertLocations = Advert::select(DB::raw("CASE
                                                            WHEN {$prefix}advert_locations.location_id = '0' and
                                                                 {$prefix}advert_locations.location_type = 'gtm' THEN '0'
                                                            ELSE merchant_id
                                                        END AS 'merchant_id'"),
                                                DB::raw("CASE
                                                            WHEN {$prefix}advert_locations.location_id = '0' and
                                                                 {$prefix}advert_locations.location_type = 'gtm' THEN 'GTM'
                                                            ELSE name
                                                        END AS 'name'"))
                                    ->leftJoin('advert_locations', 'advert_locations.advert_id', '=', 'adverts.advert_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'advert_locations.location_id')
                                    ->where('adverts.advert_id', '=', $advert_id);


            $_advertLocations = clone $advertLocations;

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
            $advertLocations->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $advertLocations)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }
                $skip = $_skip;
            });
            $advertLocations->skip($skip);

            $listOfadvertLocations = $advertLocations->get();

            $totaladvertLocations = RecordCounter::create($_advertLocations)->count();
            $totalReturnedRecords = count($listOfadvertLocations);

            $data = new stdclass();
            $data->total_records = $totaladvertLocations;
            $data->returned_records = $totalReturnedRecords;
            $data->remaining_records = $totaladvertLocations - $totalReturnedRecords;
            $data->records = $listOfadvertLocations;

            if ($totaladvertLocations === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.advert');
            }

            if ($totaladvertLocations === 1 && $listOfadvertLocations[0]->merchant_id === NULL) {
                $data->total_records = 0;
                $data->returned_records = 0;
                $data->remaining_records = 0;
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.advert');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.advertlocations.getadvertlocations.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.advertlocations.getadvertlocations.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.advertlocations.getadvertlocations.query.error', array($this, $e));

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
            Event::fire('orbit.advertlocations.getadvertlocations.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = 'null';
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.advertlocations.getadvertlocations.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Get City based on advert and country
     *
     * @author kadek<kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string `advert_id (required) - Advert id
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getAdvertCities()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.advertlocations.getadvertcity.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.advertlocations.getadvertcity.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.advertlocations.getadvertcity.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.advertlocations.getadvertcity.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $advertId = OrbitInput::get('advert_id');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'advert_id' => $advertId,
                ),
                array(
                    'advert_id' => 'required',
                )
            );

            Event::fire('orbit.advertlocations.getadvertcity.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.advertlocations.getadvertcity.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.coupon.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }

            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.coupon.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $prefix = DB::getTablePrefix();

            $AdvertCity = AdvertCity::select('advert_cities.advert_city_id',
                                             'advert_cities.advert_id',
                                             'advert_cities.mall_city_id',
                                             'mall_cities.city')
                                    ->leftJoin('mall_cities','mall_cities.mall_city_id','=','advert_cities.mall_city_id')
                                    ->where('advert_id', '=', $advertId);

            $_AdvertCity = clone $AdvertCity;

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
            $AdvertCity->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $AdvertCity)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }
                $skip = $_skip;
            });
            $AdvertCity->skip($skip);

            $listOfAdvertCity = $AdvertCity->get();

            $totalAdvertCity = RecordCounter::create($_AdvertCity)->count();
            $totalReturnedRecords = count($listOfAdvertCity);

            $data = new stdclass();
            $data->total_records = $totalAdvertCity;
            $data->returned_records = $totalReturnedRecords;
            $data->records = $listOfAdvertCity;

            if ($totalAdvertCity === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.advert');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.advertlocations.getadvertcity.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.advertlocations.getadvertcity.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.advertlocations.getadvertcity.query.error', array($this, $e));

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
            Event::fire('orbit.advertlocations.getadvertcity.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = 'null';
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.advertlocations.getadvertcity.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        $user = $this->api->user;
        // Check the existance of mall id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) use ($user){
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}