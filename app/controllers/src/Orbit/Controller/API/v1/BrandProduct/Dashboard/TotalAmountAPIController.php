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
use PaymentTransaction;
use Order;
use Exception;
use App;
use Carbon\Carbon;

class TotalAmountAPIController extends ControllerAPI
{

    /**
     * Get Month to date Total successful order amount
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

            // @todo: Cache the result based on the brand and/or merchant ids

            // minus 7 hour GMT+7
            $start = Carbon::now()->startOfMonth()->subHours(7);
            $end = Carbon::now()->subHours(7);

            // @todo: add filter to select all brands if user_type is GTM Admin
            $orders = Order::selectRaw(
                    'sum(total_amount) as sum_amount'
                )
                ->where('brand_id', $brandId)
                ->where('status', 'done')
                ->where('created_at', '>=', $start)
                ->where('created_at', '<=', $end);

            if ($userType !== 'brand') {
                $orders->whereIn('merchant_id', $merchantIds);
            }

            $sum = $orders->first();

            $this->response->data = $sum->sum_amount;
        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
