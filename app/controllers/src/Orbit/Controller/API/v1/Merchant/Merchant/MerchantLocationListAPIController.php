<?php namespace Orbit\Controller\API\v1\Merchant\Merchant;

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
use Validator;
use Lang;
use DB;
use Config;
use stdclass;
use Orbit\Controller\API\v1\Merchant\Merchant\MerchantHelper;
use App;

class MerchantLocationListAPIController extends ControllerAPI
{
    protected $merchantViewRoles = ['super admin', 'merchant database admin'];

    /**
     * GET Search Base Merchant Locations
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getSearchMerchantLocation()
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
            $validRoles = $this->merchantViewRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $merchantHelper = MerchantHelper::create();
            $merchantHelper->merchantCustomValidator();

            $sort_by = OrbitInput::get('sortby');
            $merchantId = OrbitInput::get('merchant_id');

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchantId,
                    'sortby' => $sort_by,
                ),
                array(
                    'merchant_id' => 'required|orbit.empty.base_merchant',
                    'sortby' => 'in:location_name,city,country',
                ),
                array(
                    'merchant_id.orbit.empty.base_merchant' => 'The merchant you specified is not found',
                    'sortby.in' => 'The sort by argument you specified is not valid, the valid values are: location_name, city, country',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $merchantLocations = BaseMerchant::select(
                    'base_stores.base_store_id',
                    'merchants.name',
                    'merchants.city',
                    'merchants.country'
                )
                ->leftJoin('base_stores', 'base_stores.base_merchant_id', '=', 'base_merchants.base_merchant_id')
                ->join('merchants', 'merchants.merchant_id', '=', 'base_stores.merchant_id')
                ->excludeDeleted('base_merchants')
                ->where('base_merchants.base_merchant_id', $merchantId);

            // Filter merchant by matching name/city pattern
            OrbitInput::get('keyword', function($keyword) use ($merchantLocations)
            {
                $merchantLocations->where(function($q) use ($keyword) {
                    $q->where('merchants.name', 'like', "%$keyword%")
                        ->orWhere('merchants.city', 'like', "%$keyword%");
                });
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_merchantLocations = clone $merchantLocations;

            $take = PaginationNumber::parseTakeFromGet('merchant');
            $merchantLocations->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $merchantLocations->skip($skip);

            // Default sort by
            $sortBy = 'merchants.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'location_name' => 'merchants.name',
                    'city' => 'merchants.city',
                    'country' => 'merchants.country',
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
            $merchantLocations->orderBy($sortBy, $sortMode);

            $totalMerchants = RecordCounter::create($_merchantLocations)->count();
            $listOfMerchants = $merchantLocations->get();

            $data = new stdclass();
            $data->total_records = $totalMerchants;
            $data->returned_records = count($listOfMerchants);
            $data->records = $listOfMerchants;

            if ($totalMerchants === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.merchant');
            }

            $this->response->data = $data;

            $baseMerchant = App::make('orbit.empty.base_merchant');
            $this->response->data->extras = new stdclass();
            $this->response->data->extras->base_merchant_name = $baseMerchant->name;


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
