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

class MerchantListAPIController extends ControllerAPI
{
    protected $merchantViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign admin','campaign owner', 'campaign employee'];

    /**
     * GET Search Base Merchant
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getSearchMerchant()
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

            $validator = Validator::make(
                array(
                    'sortby' => $sort_by,
                ),
                array(
                    'sortby' => 'in:merchant_name,location_number',
                ),
                array(
                    'sortby.in' => Lang::get('validation.orbit.empty.merchant_sortby_2'),
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $merchants = BaseMerchant::select(
                    'base_merchants.base_merchant_id',
                    'name',
                    DB::raw("
                            count({$prefix}base_stores.base_store_id) as location_count
                        ")
                )
                ->leftJoin('base_stores', 'base_stores.base_merchant_id', '=', 'base_merchants.base_merchant_id')
                ->excludeDeleted('base_merchants');

            OrbitInput::get('merchant_id', function($data) use ($merchants)
            {
                $merchants->whereIn('merchants.merchant_id', $data);
            });

            // Filter merchant by name
            OrbitInput::get('name', function($name) use ($merchants)
            {
                $merchants->whereIn('merchants.name', $name);
            });

            // Filter merchant by matching name pattern
            OrbitInput::get('name_like', function($name) use ($merchants)
            {
                $merchants->where('merchants.name', 'like', "%$name%");
            });

            $merchants->groupBy('base_merchants.base_merchant_id');

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_merchants = clone $merchants;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $merchants->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $merchants->skip($skip);

            // Default sort by
            $sortBy = 'base_merchants.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'merchant_name' => 'base_merchants.name',
                    'location_number' => 'location_count'
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
            $merchants->orderBy($sortBy, $sortMode);

            $totalMerchants = RecordCounter::create($_merchants)->count();
            $listOfMerchants = $merchants->get();

            $data = new stdclass();
            $data->total_records = $totalMerchants;
            $data->returned_records = count($listOfMerchants);
            $data->records = $listOfMerchants;

            if ($totalMerchants === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.merchant');
            }

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
