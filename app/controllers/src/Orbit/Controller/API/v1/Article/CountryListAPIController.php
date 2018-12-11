<?php namespace Orbit\Controller\API\v1\Article;

use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use BaseMerchant;
use Country;
use Mall;
use Validator;
use Lang;
use DB;
use Config;
use stdclass;

class CountryListAPIController extends ControllerAPI
{
    protected $roles = ['article writer', 'article publisher'];

    /**
     * GET Search Country
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getSearchCountry()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->roles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $sort_by = OrbitInput::get('sortby');

            $validator = Validator::make(
                array(
                    'sortby' => $sort_by,
                ),
                array(
                    'sortby' => 'in:name',
                ),
                array(
                    'sortby.in' => 'The sort by argument you specified is not valid, the valid values are: name',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $mallCountry = Mall::groupBy('country')->lists('country');
            $countries = Country::select('country_id', 'name', 'code')->whereIn('name', $mallCountry);

            // Filter country by name
            OrbitInput::get('name', function($name) use ($countries)
            {
                $countries->where('name', $name);
            });

            // Filter country by matching name pattern
            OrbitInput::get('name_like', function($name) use ($countries)
            {
                $countries->where('name', 'like', "%$name%");
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_countries = clone $countries;

            $take = PaginationNumber::parseTakeFromGet('country');
            $countries->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $countries->skip($skip);

            // Default sort by
            $sortBy = 'name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'name' => 'name'
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $countries->orderBy($sortBy, $sortMode);

            $totalCountries = RecordCounter::create($_countries)->count();
            $listOfCountries = $countries->get();

            $data = new stdclass();
            $data->total_records = $totalCountries;
            $data->returned_records = count($listOfCountries);
            $data->records = $listOfCountries;

            $this->response->data = $data;
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
        }

        $output = $this->render($httpCode);

        return $output;
    }
}
