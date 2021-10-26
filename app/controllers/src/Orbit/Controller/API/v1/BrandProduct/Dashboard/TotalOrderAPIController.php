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

class TotalOrderAPIController extends ControllerAPI
{

    /**
     * Get Month to date Count successful order
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

            // minus 7 hour GMT+7
            $start = Carbon::now()->startOfMonth()->subHours(7);
            $end = Carbon::now()->subHours(7);

            // @todo: add filter to select all brands if user_type is GTM Admin
            $done = Order::selectRaw(
                    'count(order_id) as count_amount'
                )
                ->where('brand_id', $brandId)
                ->where('status', Order::STATUS_DONE)
                ->where('created_at', '>=', $start)
                ->where('created_at', '<=', $end);

            if ($userType === 'store') {
                $done->whereIn('merchant_id', $merchantIds);
            }

            $done = $done->first();

            $ready = Order::selectRaw(
                    'count(order_id) as count_amount'
                )
                ->where('brand_id', $brandId)
                ->where('status', Order::STATUS_READY_FOR_PICKUP)
                ->where('created_at', '>=', $start)
                ->where('created_at', '<=', $end);

            if ($userType === 'store') {
                $ready->whereIn('merchant_id', $merchantIds);
            }

            $ready = $ready->first();

            $pending = Order::selectRaw(
                    'count(order_id) as count_amount'
                )
                ->where('brand_id', $brandId)
                ->where('status', Order::STATUS_PENDING)
                ->where('created_at', '>=', $start)
                ->where('created_at', '<=', $end);

            if ($userType === 'store') {
                $pending->whereIn('merchant_id', $merchantIds);
            }

            $pending = $pending->first();

            $data = new stdclass();
            $data->pending = $pending->count_amount;
            $data->ready = $ready->count_amount;
            $data->done = $done->count_amount;


            $this->response->data = $data;
        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
