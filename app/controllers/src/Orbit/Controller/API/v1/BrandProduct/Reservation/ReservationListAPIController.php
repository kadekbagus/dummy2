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
use BppUserMerchant;

class ReservationListAPIController extends ControllerAPI
{

    /**
     * List brand product reservation for BPP
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
            $merchantIds = $this->getStoreIds($userId);

            $status = OrbitInput::get('status', null);

            $validator = Validator::make(
                array(
                    'status'      => $status,
                ),
                array(
                    'status'      => 'in:pending,accepted,cancelled,declined,expired,done,sold',
                )
            );

            $prefix = DB::getTablePrefix();

            $reservations = BrandProductReservation::select(DB::raw("
                    {$prefix}brand_product_reservations.brand_product_reservation_id,
                    {$prefix}brand_product_reservation_details.selling_price,
                    {$prefix}brand_product_reservations.created_at,
                    {$prefix}brand_product_reservations.expired_at,
                    {$prefix}brand_product_reservations.total_amount,
                    {$prefix}brand_product_reservation_details.quantity,
                    {$prefix}brand_product_reservations.user_id,
                    {$prefix}brand_product_reservation_details.product_name,
                    {$prefix}brand_product_reservation_details.brand_product_variant_id,
                    CONCAT({$prefix}users.user_firstname,' ',{$prefix}users.user_lastname) as username,
                    CASE {$prefix}brand_product_reservations.status
                        WHEN 'accepted' THEN
                            CASE WHEN {$prefix}brand_product_reservations.expired_at < NOW()
                                THEN 'expired'
                                ELSE {$prefix}brand_product_reservations.status
                            END
                        WHEN 'done' THEN 'sold'
                        ELSE
                            {$prefix}brand_product_reservations.status
                    END as status_label
                "))
                ->with([
                    'users' => function($q) {
                        $q->select('users.user_id', 'user_firstname', 'user_lastname');
                    },
                    'store' => function($q) {
                        $q->select('merchants.merchant_id', 'merchants.name');
                    },
                    'details' => function($q) {
                        $q->with([
                            'variant_details' => function($q12) {
                                $q12->select('brand_product_reservation_detail_id', 'brand_product_reservation_variant_detail_id', 'value')
                                    ->where('option_type', 'variant_option');
                            }
                        ]);
                    }
                ])
                ->leftJoin('brand_product_reservation_details', 'brand_product_reservation_details.brand_product_reservation_id', '=', 'brand_product_reservations.brand_product_reservation_id')
                ->leftJoin('brand_product_reservation_variant_details', 'brand_product_reservation_variant_details.brand_product_reservation_detail_id', '=', 'brand_product_reservation_details.brand_product_reservation_detail_id')
                ->leftjoin('users', 'users.user_id', '=', 'brand_product_reservations.user_id')
                ->where(DB::raw("{$prefix}brand_product_reservations.brand_id"), $brandId)
                ->groupBy(DB::raw("{$prefix}brand_product_reservations.brand_product_reservation_id"));

            ($userType == 'store' && count($merchantIds)>0) ? $reservations->whereIn('brand_product_reservations.merchant_id', $merchantIds) : null;

            OrbitInput::get('product_name_like', function($keyword) use ($reservations)
            {
                $reservations->where(function($query) use($keyword, $reservations)
                {
                    $query->where('product_name', 'like', "%$keyword%")
                        ->orWhere('brand_product_reservations.brand_product_reservation_id', 'like', "%{$keyword}%");
                });
            });

            OrbitInput::get('reservation_id', function($reservationId) use ($reservations)
            {
                $reservations->where('brand_product_reservations.brand_product_reservation_id', $reservationId);
            });

            OrbitInput::get('status', function($status) use ($reservations)
            {
                switch (strtolower($status)) {
                    case 'expired':
                        $reservations->where(function ($q) {
                            $q->where(function ($q2) {
                                $q2->where('brand_product_reservations.status', 'accepted')
                                    ->where('expired_at', '<', DB::raw("NOW()"));
                            })->orWhere('brand_product_reservations.status', 'expired');
                        });
                        break;

                    case 'accepted':
                        $reservations->where('brand_product_reservations.status', 'accepted')
                            ->where('expired_at', '>=', DB::raw("NOW()"));
                        break;

                    case 'sold':
                    case 'done':
                        $reservations->where('brand_product_reservations.status', 'done');
                        break;

                    default:
                        $reservations->where('brand_product_reservations.status', $status);
                        break;
                }
            });

            OrbitInput::get('username', function($username) use ($reservations)
            {
                $reservations->having('username',  'like', "%$username%");
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

            $returnedData = [];
            foreach ($listOfItems as $item) {
                $returnedItem = new stdclass();
                $returnedItem->brand_product_reservation_id = $item->brand_product_reservation_id;
                $returnedItem->user_name = $item->users->user_firstname . ' ' . $item->users->user_lastname;
                $returnedItem->created_at = (string) $item->created_at;
                $returnedItem->expired_at = $item->expired_at;
                $returnedItem->status = $item->status_label;
                $returnedItem->total_amount = $item->total_amount;
                $returnedItem->details = [];

                foreach ($item->details as $detail) {
                    $dtl = new stdclass();

                    $dtl->product_name = $detail->product_name;
                    $dtl->quantity = $detail->quantity;
                    $dtl->selling_price = $detail->selling_price;
                    $dtl->original_price = $detail->original_price;
                    $dtl->image_url = $detail->image_url;
                    $dtl->image_cdn = $detail->image_cdn;

                    $variants = [];
                    foreach ($detail->variant_details as $variantDetail) {
                        $variants[] = strtoupper($variantDetail->value);
                    }
                    $dtl->variants = implode(', ', $variants);
                    $returnedItem->details[] = $dtl;
                }

                $returnedData[] = $returnedItem;
            }

            $data = new stdclass();
            $data->total_records = $totalItems;
            $data->returned_records = count($listOfItems);
            $data->records = $returnedData;

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

    public function getStoreIds($bpp_user_id)
    {
        $storeIds = [];
        $stores = BppUserMerchant::select('merchant_id')->where('bpp_user_id', '=', $bpp_user_id)->get();

        foreach($stores as $key => $value) {
            $storeIds[] = $value->merchant_id;
        }

        return $storeIds;
    }
}
