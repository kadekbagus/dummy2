<?php

namespace Orbit\Controller\API\v1\BrandProduct\Dashboard;

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
use Order;
use Exception;
use App;
use Carbon\Carbon;

class TotalSoldOrderAPIController extends ControllerAPI
{

    /**
     * Get total sold order
     *
     * @author ahmad <ahmad@gotomalls.com>
     */
    public function get()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $brandId = $user->base_merchant_id;
            $userType = $user->user_type;
            $stores = $user->stores()->get();
            $merchantIds = [];

            foreach ($stores as $store) {
                $merchantIds[] = $store->merchant_id;
            }

            // @todo: Cache the result based on the brand and/or merchant ids

            // @todo: add filter to select all brands if user_type is GTM Admin
            $done = Order::selectRaw(
                    'count(order_id) as count_amount'
                )
                ->where('status', Order::STATUS_DONE);
            
            ($userType === 'gtm_admin') ? null : $done->where('brand_id', $brandId);

            if ($userType === 'store') {
                $done->whereIn('merchant_id', $merchantIds);
            }

            $done = $done->first();

            $data = new stdclass();
            $data->total_sold_orders = $done->count_amount;

            $this->response->data = $data;
        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
