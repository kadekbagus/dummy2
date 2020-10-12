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
use DB;
use Lang;
use Config;
use BrandProductReservation;
use Exception;
use App;

class ReservationListAPIController extends ControllerAPI
{

    /**
     * List brand product reservation for BPP
     * @todo: add status param validation
     *
     * @author Ahmad <ahmad@dominopos.com>
     */
    public function getSearchReservation()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $userType = $user->user_type;
            $brandId = $user->base_merchant_id;
            $merchantId = $user->merchant_id;

            $status = OrbitInput::get('status', null);

            $validator = Validator::make(
                array(
                    'status'      => $status,
                ),
                array(
                    'status'      => 'in:pending,accepted,cancelled,declined,expired',
                )
            );

            $prefix = DB::getTablePrefix();

            $reservations = BrandProductReservation::select(DB::raw("
                    {$prefix}brand_product_reservations.brand_product_reservation_id,
                    {$prefix}brand_product_reservations.selling_price,
                    {$prefix}brand_product_reservations.created_at,
                    {$prefix}brand_product_reservations.expired_at,
                    {$prefix}brand_product_reservations.quantity,
                    {$prefix}brand_product_reservations.product_name,
                    CASE WHEN {$prefix}brand_product_reservations.expired_at < NOW()
                        THEN 'expired'
                        ELSE {$prefix}brand_product_reservations.status
                    END as status
                "))
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
                ->where(DB::raw("{$prefix}brand_product_reservations.brand_id"), $brandId)
                ->where('option_type', 'merchant')
                ->groupBy(DB::raw("{$prefix}brand_product_reservations.brand_product_reservation_id"));

            if (! empty($merchantId)) {
                $reservations->where('brand_product_reservation_details.option_id', $merchantId);
            }

            OrbitInput::get('product_name_like', function($keyword) use ($reservations)
            {
                $reservations->where('product_name', 'like', "%$keyword%");
            });

            OrbitInput::get('status', function($status) use ($reservations)
            {
                if ($status == 'expired') {
                    $reservations->where('expired_at', '<', DB::raw("NOW()"));
                } else {
                    $reservations->where('status', $status);
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_reservations = clone $reservations;

            // @todo: change the parseTakeFromGet to brand_product_reservation
            $take = PaginationNumber::parseTakeFromGet('merchant');
            $reservations->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $reservations->skip($skip);

            // Default sort by
            $sortBy = 'created_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'created_at' => 'brand_product_reservations.created_at',
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
            $reservations->orderBy($sortBy, $sortMode);

            $totalItems = RecordCounter::create($_reservations)->count();
            $listOfItems = $reservations->get();

            $data = new stdclass();
            $data->total_records = $totalItems;
            $data->returned_records = count($listOfItems);
            $data->records = $listOfItems;

            if ($totalItems === 0) {
                $data->records = NULL;
                $this->response->message = "There is no reservations that matched your search criteria";
            }

            $this->response->data = $data;
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
