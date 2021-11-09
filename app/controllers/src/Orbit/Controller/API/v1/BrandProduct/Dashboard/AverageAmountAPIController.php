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

class AverageAmountAPIController extends ControllerAPI
{

    /**.
     * Get Month to date Average amount per order
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

            // @todo: add filter to select all brands if user_type is GTM Admin
            $data = Order::selectRaw(
                    'avg(total_amount) as avg_amount'
                )
                ->where('status', Order::STATUS_DONE)
                ->where('created_at', '>=', $start);
            
            ($userType === 'gtm_admin') ? null : $data->where('brand_id', $brandId);

            if ($userType === 'store') {
                $data->whereIn('merchant_id', $merchantIds);
            }

            $data = $data->first();

            $this->response->data = $data->avg_amount;
        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
