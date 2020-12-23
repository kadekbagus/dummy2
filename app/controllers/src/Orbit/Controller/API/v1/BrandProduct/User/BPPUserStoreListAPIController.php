<?php

namespace Orbit\Controller\API\v1\BrandProduct\User;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use App;
use DB;
use stdclass;
use BaseStore;
use Exception;
use Validator;

class BPPUserStoreListAPIController extends ControllerAPI
{

    /**
     * Store list for user creation on brand product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function getSearchStore()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');

            $userId = $user->bpp_user_id;
            $userType = $user->user_type;
            $brandId = $user->base_merchant_id;

            $sortBy = OrbitInput::get('sortby', null);
            $sortMode = OrbitInput::get('sortmode', null);

            $validator = Validator::make(
                array(
                    'sortBy'      => $sortBy,
                    'sortMode'    => $sortMode,
                ),
                array(
                    'sortBy'      => 'in:store_name',
                    'sortMode'    => 'in:asc,desc',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();
            
            $stores = BaseStore::select(DB::raw("m1.merchant_id"), DB::raw("CONCAT(m1.name,' ', m2.name) as store_name"))
                                ->join(DB::raw("{$prefix}merchants as m1"), DB::raw('m1.merchant_id'), '=', 'base_stores.base_store_id')
                                ->join(DB::raw("{$prefix}merchants as m2"), DB::raw('m2.merchant_id'), '=', DB::raw('m1.parent_id'))
                                ->where('base_stores.base_merchant_id', $brandId)
                                ->where('base_stores.status', 'active');

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_stores = clone $stores;

            // @todo: change the parseTakeFromGet to bpp_users
            $take = PaginationNumber::parseTakeFromGet('merchant');
            $stores->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $stores->skip($skip);

            // Default sort by
            $sortBy = DB::raw("store_name");
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'store_name' => DB::raw("store_name")
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

            $data = new stdclass();
            $data->total_records = $totalItems;
            $data->returned_records = count($listOfItems);
            $data->records = $listOfItems;

            if ($totalItems === 0) {
                $data->records = NULL;
                $this->response->message = "There is no store that matched your search criteria";
            }

            $this->response->data = $data;

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
