<?php

namespace Orbit\Controller\API\v1\BrandProduct\User;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use stdclass;
use BppUser;
use DB;
use Exception;
use App;

class BPPUserListAPIController extends ControllerAPI
{

    /**
     * User list on brand product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function getSearchUser()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');

            $userId = $user->bpp_user_id;
            $userType = $user->user_type;
            $brandId = $user->base_merchant_id;
            $merchantId = $user->merchant_id;

            $sortBy = OrbitInput::get('sortby', null);
            $sortMode = OrbitInput::get('sortmode', null);

            $validator = Validator::make(
                array(
                    'sortBy'      => $sortBy,
                    'sortMode'    => $sortMode,
                ),
                array(
                    'sortBy'      => 'in:status,updated_at,created_at,city,store_name,name,email',
                    'sortMode'    => 'in:asc,desc',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();
            
            $BPPUsers = BppUser::select('bpp_users.bpp_user_id',
                                        DB::raw("CONCAT(m1.name,' ', m2.name) as store_name"),
                                        'bpp_users.name',
                                        'bpp_users.email',
                                        'bpp_users.status',
                                        DB::raw("m2.city")
                                        )
                                ->join(DB::raw("{$prefix}merchants as m1"), DB::raw('m1.merchant_id'), '=', 'bpp_users.merchant_id')
                                ->join(DB::raw("{$prefix}merchants as m2"), DB::raw('m2.merchant_id'), '=', DB::raw('m1.parent_id'))
                                ->where('bpp_users.user_type', 'store');

            if ($userType === 'brand') {
                $BPPUsers->where('bpp_users.base_merchant_id', $brandId);
            } else {
                $BPPUsers->where('bpp_users.bpp_user_id', $userId);
            }

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_BPPUsers = clone $BPPUsers;

            // @todo: change the parseTakeFromGet to bpp_users
            $take = PaginationNumber::parseTakeFromGet('merchant');
            $BPPUsers->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $BPPUsers->skip($skip);

            // Default sort by
            $sortBy = 'bpp_users.updated_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'status' => 'bpp_users.status',
                    'updated_at' => 'bpp_users.updated_at',
                    'created_at' => 'bpp_users.created_at',
                    'name' => 'bpp_users.name',
                    'email' => 'bpp_users.email',
                    'city' => DB::raw("m2.city"),
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
            $BPPUsers->orderBy($sortBy, $sortMode);

            $totalItems = RecordCounter::create($_BPPUsers)->count();
            $listOfItems = $BPPUsers->get();

            $data = new stdclass();
            $data->total_records = $totalItems;
            $data->returned_records = count($listOfItems);
            $data->records = $listOfItems;

            if ($totalItems === 0) {
                $data->records = NULL;
                $this->response->message = "There is no users that matched your search criteria";
            }

            $this->response->data = $data;

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
