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

use Lang;
use Config;
use BrandProductReservation;
use Exception;
use App;

class ReservationListAPIController extends ControllerAPI
{

    /**
     * List brand product reservation for BPP
     * @todo: add store level filter
     *
     * @author Ahmad <ahmad@dominopos.com>
     */
    public function getSearchReservation()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $brandId = $user->base_merchant_id;

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
                ->where('brand_id', $brandId);

            OrbitInput::get('product_name_like', function($keyword) use ($reservations)
            {
                $reservations->where('product_name', 'like', "%$keyword%");
            });

            OrbitInput::get('status', function($status) use ($reservations)
            {
                $reservations->where('status', $status);
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
