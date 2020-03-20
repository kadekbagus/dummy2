<?php

namespace Orbit\Controller\API\v1\BrandProduct\Reservation;

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

use Lang;
use Config;
use BrandProductReservation;
use Exception;
use App;

class ReservationDetailAPIController extends ControllerAPI
{

    /**
     * List brand product reservation for BPP
     *
     * @author Ahmad <ahmad@dominopos.com>
     */
    public function getReservationDetail()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $userType = $user->user_type;
            $brandId = $user->base_merchant_id;
            $merchantId = $user->merchant_id;

            $reservations = BrandProductReservation::select(
                    'brand_product_reservation_id',
                    'selling_price',
                    'created_at',
                    'expired_at',
                    'status',
                    'quantity',
                    'product_name'
                )
                ->with([
                    'users' => function($q) {
                        $q->select('user_firstname', 'user_lastname');
                    },
                    'details' => function($q) {
                        $q->select('value')
                            ->with('stores.mall');
                    },
                    'product.mediaMain' => function($q) {
                        $q->select('path', 'cdn_url');
                    }
                ])
                ->leftJoin('brand_product_reservation_details', 'brand_product_reservation_details.brand_product_reservation_id', '=', 'brand_product_reservations.brand_product_reservation_id')
                ->where('brand_id', $brandId)
                ->where('option_type', 'merchant');

            if (! empty($merchantId)) {
                $reservations->where('brand_product_reservation_details.option_id', $merchantId);
            }

            $reservations->firstOrFail();

            $this->response->data = $reservations;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (\Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render($httpCode);
    }

}
