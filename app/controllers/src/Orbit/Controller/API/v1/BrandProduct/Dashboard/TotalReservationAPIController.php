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
use BrandProductReservation;
use Exception;
use App;
use Carbon\Carbon;

class TotalReservationAPIController extends ControllerAPI
{

    /**.
     * Get Month to date Count successful reservation
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
            $done = BrandProductReservation::selectRaw(
                    'count(brand_product_reservation_id) as count_amount'
                )
                ->where('brand_id', $brandId)
                ->where('status', BrandProductReservation::STATUS_DONE)
                ->where('created_at', '>=', $start)
                ->where('created_at', '<=', $end);

            if ($userType !== 'brand') {
                $done->whereIn('merchant_id', $merchantIds);
            }

            $done = $done->first();

            $accepted = BrandProductReservation::selectRaw(
                    'count(brand_product_reservation_id) as count_amount'
                )
                ->where('brand_id', $brandId)
                ->where('status', BrandProductReservation::STATUS_ACCEPTED)
                ->where('created_at', '>=', $start)
                ->where('created_at', '<=', $end);

            if ($userType !== 'brand') {
                $accepted->whereIn('merchant_id', $merchantIds);
            }

            $accepted = $accepted->first();

            $pending = BrandProductReservation::selectRaw(
                    'count(brand_product_reservation_id) as count_amount'
                )
                ->where('brand_id', $brandId)
                ->where('status', BrandProductReservation::STATUS_PENDING)
                ->where('created_at', '>=', $start)
                ->where('created_at', '<=', $end);

            if ($userType !== 'brand') {
                $pending->whereIn('merchant_id', $merchantIds);
            }

            $pending = $pending->first();

            $data = new stdclass();
            $data->pending = $pending->count_amount;
            $data->accepted = $accepted->count_amount;
            $data->done = $done->count_amount;


            $this->response->data = $data;
        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
