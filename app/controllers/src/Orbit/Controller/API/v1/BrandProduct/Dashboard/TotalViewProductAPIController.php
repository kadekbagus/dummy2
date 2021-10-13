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
use Exception;
use App;
use DB;
use Carbon\Carbon;

class TotalViewProductAPIController extends ControllerAPI
{

    /**.
     * Get total product detail page view for brands
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
            $data = Activity::select(
                    DB::raw('count(activity_id) as total_view')
                )
                ->where('object_id', $brandId)
                ->where('object_name', 'BaseMerchant')
                ->where('activity_name', 'view_instore_bp_detail_page')
                ->where('created_at', '>=', $start)
                ->where('created_at', '<=', $end)
                ->groupBy('product_id');

            $data = $data->first();

            $this->response->data = $data->total_view;
        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
