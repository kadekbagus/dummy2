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
            $stores = $user->stores()->get();
            $merchantIds = [];

            foreach ($stores as $store) {
                $merchantIds[] = $store->merchant_id;
            }

            // @todo: Cache the result based on the brand and/or merchant ids

            // @todo: add filter to select all brands if user_type is GTM Admin
            $awaitingActionStatus = [BrandProductReservation::STATUS_PENDING,
                                    BrandProductReservation::STATUS_ACCEPTED,
                                    BrandProductReservation::STATUS_PICKED_UP,
                                    BrandProductReservation::STATUS_NOT_DONE];

            $awaitingActions = BrandProductReservation::selectRaw('count(brand_product_reservation_id) as count_amount')
                                                    ->whereIn('status', $awaitingActionStatus);

            ($userType === 'gtm_admin') ? null : $awaitingActions->where('brand_id', $brandId);

            if ($userType === 'store') {
                $awaitingActions->whereIn('merchant_id', $merchantIds);
            }

            $awaitingActions = $awaitingActions->first();

            $data = new stdclass();
            $data->reservation_awaiting_actions = $awaitingActions->count_amount;

            $this->response->data = $data;
        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }

}
