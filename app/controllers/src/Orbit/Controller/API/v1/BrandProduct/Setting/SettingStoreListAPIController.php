<?php

namespace Orbit\Controller\API\v1\BrandProduct\Setting;

use DB;
use App;
use Lang;
use Tenant;
use StdClass;
use Exception;
use Validator;
use DominoPOS\OrbitACL\ACL;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\ControllerAPI;
use Illuminate\Database\QueryException;
use Orbit\Helper\Util\PaginationNumber;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;

class SettingStoreListAPIController extends ControllerAPI
{

    /**
     * Show list of store with settings.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function getSearchStore()
    {
        try {
            $httpCode = 200;
            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $brandId = $user->base_merchant_id;
            $userType = $user->user_type;

            $status = OrbitInput::get('status', null);
            $sortBy = OrbitInput::get('sortby', null);
            $sortMode = OrbitInput::get('sortmode', null);

            $validator = Validator::make(
                array(
                    'status'      => $status,
                    'sortBy'      => $sortBy,
                    'sortMode'    => $sortMode,
                ),
                array(
                    'status'      => 'in:inactive,active',
                    'sortBy'      => 'in:created_at,updated_at,status',
                    'sortMode'    => 'in:asc,desc',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            // Brand user
            $stores = Tenant::select('merchants.merchant_id',
									 'merchants.status',
									 'merchants.reservation_commission',
									 'merchants.purchase_commission',
									 DB::raw("CONCAT({$prefix}merchants.name,' ', m1.name) as store_name"))
								->join('base_stores', 'base_stores.base_store_id', '=', 'merchants.merchant_id')
								->join('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
								->join(DB::raw("{$prefix}merchants as m1"), DB::raw('m1.merchant_id'), '=', 'merchants.parent_id')
								->where('base_merchants.base_merchant_id', '=', $brandId);

            // Store user
            if ($userType === 'store') {
                $stores = Tenant::select('merchants.merchant_id',
									     'merchants.status',
									     'merchants.reservation_commission',
									     'merchants.purchase_commission',
									     DB::raw("CONCAT({$prefix}merchants.name,' ', m1.name) as store_name"))
                                    ->join('bpp_user_merchants', 'bpp_user_merchants.merchant_id', '=', 'merchants.merchant_id')
                                    ->join(DB::raw("{$prefix}merchants as m1"), DB::raw('m1.merchant_id'), '=', 'merchants.parent_id')
                                    ->where('bpp_user_merchants.bpp_user_id', $userId);
            }

            OrbitInput::get('status', function($status) use ($stores)
            {
                $stores->where('merchants.status', $status);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_stores = clone $stores;

            // @todo: change the parseTakeFromGet to brand_products
            $take = PaginationNumber::parseTakeFromGet('merchant');
            $stores->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $stores->skip($skip);

            // Default sort by
            $sortBy = 'merchants.updated_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'status' => 'merchants.status',
                    'updated_at' => 'merchants.updated_at',
                    'created_at' => 'merchants.created_at',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });

            $stores->orderBy($sortBy, $sortMode);

            $totalItems = RecordCounter::create($_stores)->count();
            $listOfItems = $stores->get();

            $data = new StdClass();
            $data->total_records = $totalItems;
            $data->returned_records = count($listOfItems);
            $data->records = $listOfItems;

            if ($totalItems === 0) {
                $data->records = NULL;
                $this->response->message = "There is no stores that matched your search criteria";
            }

            $this->response->data = $data;

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
