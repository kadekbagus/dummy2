<?php
/**
 * An API controller for managing countries.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Helper\EloquentRecordCounter as RecordCounter;

class CountryAPIController extends ControllerAPI
{
    /**
     * GET - List of countries
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param array     `country_ids`   (optional) - IDs of country
     * @param array     `names`         (optional) - Names of country
     * @param array     `codes`         (optional) - Code of coutnry (2 char)
     * @param integer   `take`          (optional) - limit
     * @param integer   `skip`          (optional) - limit offset
     * @param string    `sort_by`       (optional) - column order by name
     * @param string    `sort_mode`     (optional) - asc or desc
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchCountry()
    {
        try {
            $httpCode = 200;

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.country.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.country.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $countries = Country::with(array());

            OrbitInput::get('country_ids', function($ids) use ($countries) {
                $ids = (array)$ids;
                $countries->whereIn('country_id', $ids);
            });

            OrbitInput::get('codes', function($codes) use ($countries) {
                $codes = (array)$codes;
                $countries->whereIn('code', $codes);
            });

            OrbitInput::get('names', function($names) use ($countries) {
                $names = (array)$names;
                $countries->whereIn('name', $names);
            });
            
            OrbitInput::get('name_like', function ($name) use ($countries) {
                $countries->where('name', 'like', "%$name%");
            });
                
            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_countries = clone $countries;

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
            $countries->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $countries) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $countries->skip($skip);

            // Default sort by
            $sortBy = 'countries.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'name'       => 'countries.name'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $countries->orderBy($sortBy, $sortMode);

            $totalCountry = RecordCounter::create($_countries)->count();
            $listCountry = $countries->get();

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
}
