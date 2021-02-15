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
                    'status'      => 'in:pending,accepted,cancelled,declined,expired,done',
                )
            );

            $prefix = DB::getTablePrefix();

            $reservations = BrandProductReservation::select(DB::raw("
                    {$prefix}brand_product_reservations.brand_product_reservation_id,
                    {$prefix}brand_product_reservations.selling_price,
                    {$prefix}brand_product_reservations.created_at,
                    {$prefix}brand_product_reservations.expired_at,
                    {$prefix}brand_product_reservations.quantity,
                    {$prefix}brand_product_reservations.user_id,
                    {$prefix}brand_product_reservations.product_name,
                    {$prefix}brand_product_reservations.brand_product_variant_id,
                    CASE {$prefix}brand_product_reservations.status
                        WHEN 'accepted' THEN
                            CASE WHEN {$prefix}brand_product_reservations.expired_at < NOW()
                                THEN 'expired'
                                ELSE {$prefix}brand_product_reservations.status
                            END
                        WHEN 'done' THEN 'sold'
                        ELSE
                            {$prefix}brand_product_reservations.status
                    END as status
                "))
                ->with([
                    'users' => function($q) {
                        $q->select('users.user_id', 'user_firstname', 'user_lastname');
                    },
                    'store' => function($q) {
                        $q->with('store.mall');
                    },
                    'brand_product_variant' => function($q) {
                        $q->select('brand_product_variant_id', 'brand_product_id');
                        $q->with([
                            'brand_product' => function($q2) {
                                $q2->select('brand_product_id');
                                $q2->with([
                                    'brand_product_main_photo' => function($q3) {
                                        $q3->select('media_id', 'object_id', 'path', 'cdn_url');
                                    }
                                ]);
                            }
                        ]);
                    },
                    'details' => function($q) {
                        $q->select('brand_product_reservation_detail_id', 'brand_product_reservation_id', 'value')
                            ->where('option_type', 'variant_option');
                    }
                ])
                ->leftJoin('brand_product_reservation_details', 'brand_product_reservation_details.brand_product_reservation_id', '=', 'brand_product_reservations.brand_product_reservation_id')
                ->where(DB::raw("{$prefix}brand_product_reservations.brand_id"), $brandId)
                ->where('option_type', 'merchant')
                ->groupBy(DB::raw("{$prefix}brand_product_reservations.brand_product_reservation_id"));

            if (! empty($merchantId)) {
                $reservations->where('brand_product_reservation_details.value', $merchantId);
            }

            OrbitInput::get('product_name_like', function($keyword) use ($reservations)
            {
                $reservations->where('product_name', 'like', "%$keyword%")
                    ->orWhere('brand_product_reservations.brand_product_reservation_id', 'like', "%{$keyword}%");
            });

            OrbitInput::get('status', function($status) use ($reservations)
            {
                switch (strtolower($status)) {
                    case 'expired':
                        $reservations->where('status', 'accepted')
                            ->where('expired_at', '<', DB::raw("NOW()"));
                        break;

                    case 'accepted':
                        $reservations->where('status', 'accepted')
                            ->where('expired_at', '>=', DB::raw("NOW()"));
                        break;

                    case 'sold':
                    case 'done':
                        $reservations->where('status', 'done');
                        break;

                    default:
                        $reservations->where('status', $status);
                        break;
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

            $returnedData = [];
            foreach ($listOfItems as $item) {
                $returnedItem = new stdclass();
                $returnedItem->brand_product_reservation_id = $item->brand_product_reservation_id;
                $returnedItem->user_name = $item->users->user_firstname . ' ' . $item->users->user_lastname;
                $returnedItem->product_name = $item->product_name;
                $returnedItem->selling_price = $item->selling_price;
                $returnedItem->created_at = (string) $item->created_at;
                $returnedItem->expired_at = $item->expired_at;
                $returnedItem->status = $item->status;
                $imgPath = '';
                $cdnUrl = '';
                if (is_object($item->brand_product_variant)) {
                    if (is_object($item->brand_product_variant->brand_product)) {
                        if (! empty($item->brand_product_variant->brand_product->brand_product_main_photo)) {
                            if (is_object($item->brand_product_variant->brand_product->brand_product_main_photo[0])) {
                                $imgPath = $item->brand_product_variant->brand_product->brand_product_main_photo[0]->path;
                                $cdnUrl = $item->brand_product_variant->brand_product->brand_product_main_photo[0]->cdn_url;
                            }
                        }
                    }
                }

                $returnedItem->img_path = $imgPath;
                $returnedItem->cdn_url = $cdnUrl;
                $variants = [];
                foreach ($item->details as $variantDetail) {
                    $variants[] = $variantDetail->value;
                }
                $returnedItem->variants = implode(',', $variants);
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

}
