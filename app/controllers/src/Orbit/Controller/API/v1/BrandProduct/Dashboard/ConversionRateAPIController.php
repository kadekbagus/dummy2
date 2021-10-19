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
use Activity;
use Order;
use Exception;
use App;
use DB;
use Media;
use Carbon\Carbon;

class ConversionRateAPIController extends ControllerAPI
{

    /**.
     * Get conversion level from how many users that view product page to how many users
     * that actually make successful order
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
            $merchantIds = [];
            foreach ($stores as $store) {
                $merchantIds[] = $store->merchant_id;
            }

            // @todo: Cache the result based on the brand and/or merchant ids

            // minus 7 hour GMT+7
            $start = Carbon::now()->startOfMonth()->subHours(7);
            $end = Carbon::now()->subHours(7);

            // @todo: add filter to select all brands if user_type is GTM Admin
            $data = Activity::select(
                    DB::raw('count(distinct user_id) as unique_user')
                )
                ->where('object_id', $brandId)
                ->where('object_name', 'BaseMerchant')
                ->where('activity_name', 'view_instore_bp_detail_page')
                ->where('created_at', '>=', $start)
                ->where('created_at', '<=', $end);

            $data = $data->first();
            $totalUniqueVisitor = $data->unique_user;

            // @todo: add filter to select all brands if user_type is GTM Admin
            $order = Order::selectRaw(
                    'count(distinct user_id) as unique_user'
                )
                ->where('brand_id', $brandId)
                ->where('status', Order::STATUS_PENDING)
                ->where('created_at', '>=', $start)
                ->where('created_at', '<=', $end);

            if ($userType === 'store') {
                $order->whereIn('merchant_id', $merchantIds);
            }

            $order = $order->first();
            $totalUniqueBuyer = $order->unique_user;

            $conversionRate = 0;
            if (! empty($totalUniqueVisitor)) {
                $conversionRate = ($totalUniqueBuyer / $totalUniqueVisitor) * 100;
            }

            $this->response->data = $conversionRate;
        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
