<?php namespace Orbit\Controller\API\v1\Merchant\Store;

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
use BaseStore;
use Validator;
use Lang;
use DB;
use Config;
use stdClass;
use \Exception;
use Orbit\Controller\API\v1\Merchant\Store\StoreHelper;

class StoreListAPIController extends ControllerAPI
{
    protected $storeViewRoles = ['super admin', 'merchant database admin'];
    /**
     * GET - get store
     *
     * @author Irianto <irianto@dominopos.com>
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
    public function getSearchStore()
    {
        $store = NULL;
        $user = NULL;
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->storeViewRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $sort_by = OrbitInput::get('sortby', 'merchant');
            $sort_mode = OrbitInput::get('sortmode','asc');

            $storeHelper = StoreHelper::create();
            $storeHelper->storeCustomValidator();

            $validator = Validator::make(
                array(
                    'sortby'   => $sort_by,
                ),
                array(
                    'sortby'   => 'in:merchant,location,created_date',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();
            $store = BaseStore::select('base_merchants.base_merchant_id',
                                DB::raw("{$prefix}base_merchants.name AS merchant"),
                                'base_stores.base_store_id',
                                'merchants.merchant_id',
                                DB::raw("{$prefix}merchants.name AS location"),
                                DB::raw("{$prefix}objects.object_name AS floor"),
                                'base_stores.unit', 'base_stores.phone',
                                'base_stores.verification_number',
                                'base_stores.created_at')
                            ->join('base_merchants', 'base_stores.base_merchant_id', '=', 'base_merchants.base_merchant_id')
                            ->leftJoin('objects', 'base_stores.floor_id', '=', 'objects.object_id')
                            ->leftJoin('merchants', 'base_stores.merchant_id', '=', 'merchants.merchant_id')
                            ->where('base_stores.status', '!=', 'deleted');

            $sortByMapping = array(
                'merchant'      => 'base_merchants.name',
                'location'      => 'merchants.name',
                'created_date'  => 'base_merchants.created_at'
            );
            $sort_by = $sortByMapping[$sort_by];

            OrbitInput::get('sortmode', function($_sortMode) use (&$sort_mode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sort_mode = 'desc';
                }
            });

            $store = $store->orderBy($sort_by, $sort_mode);

            $_store = clone $store;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $store->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $store->skip($skip);

            $storeList = $store->get();
            $count = count($_store->get());

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($storeList);
            $this->response->data->records = $storeList;
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
}
