<?php

namespace Orbit\Controller\API\v1\Pub\Reservation;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Lang;
use \Exception;
use BrandProductReservation;
use Helper\EloquentRecordCounter as RecordCounter;

class ReservationPurchasedListAPIController extends PubControllerAPI
{
    public function getReservationPurchasedList()
    {
        $httpCode = 200;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $language = OrbitInput::get('language', 'id');

            $validator = Validator::make(
                array(
                    'language' => $language,
                ),
                array(
                    'language' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
            if ($usingCdn) {
                $image = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
            }

            $reservation = BrandProductReservation::select('brand_product_reservations.brand_product_reservation_id',
                                                           'brand_product_reservation_details.product_name',
                                                           'brand_product_reservation_details.selling_price',
                                                           'brand_product_reservations.created_at',
                                                           'brand_product_reservations.expired_at',
                                                           'brand_product_reservations.status',
                                                           'brand_product_variants.brand_product_id',
                                                    DB::raw("{$image} as image"),
                                                    DB::raw("CONCAT(m1.name,' ', m2.name) as store_name"))
                                                    ->join(DB::raw("{$prefix}merchants as m1"), DB::raw('m1.merchant_id'), '=', 'brand_product_reservations.merchant_id')
                                                    ->join(DB::raw("{$prefix}merchants as m2"), DB::raw('m2.merchant_id'), '=', DB::raw('m1.parent_id'))
                                                    ->join(
                                                        'brand_product_reservation_details',
                                                        'brand_product_reservations.brand_product_reservation_id',
                                                        '=',
                                                        'brand_product_reservation_details.brand_product_reservation_id'
                                                    )
                                                    ->leftjoin(
                                                        'brand_product_variants',
                                                        'brand_product_reservation_details.brand_product_variant_id',
                                                        '=',
                                                        'brand_product_variants.brand_product_variant_id'
                                                    )
                                                    ->leftjoin('media', function ($join) {
                                                        $join->on('media.object_id', '=', 'brand_product_variants.brand_product_id')
                                                            ->where('media.media_name_id', '=', 'brand_product_main_photo')
                                                            ->where('media.media_name_long', '=', 'brand_product_main_photo_orig')
                                                            ->where('media.object_name', '=', 'brand_product');
                                                    })
                                                    ->where('brand_product_reservations.user_id', '=', $user->user_id);


            $reservation = $reservation->orderBy(DB::raw("{$prefix}brand_product_reservations.created_at"), 'desc');

            $_reservation = clone $reservation;
            $_reservation->groupBy('brand_product_reservations.brand_product_reservation_id');

            $take = PaginationNumber::parseTakeFromGet('coupon');
            $reservation->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $reservation->skip($skip);

            $listReservation = $this->transformReservationList($reservation);
            $count = RecordCounter::create($_reservation)->count();

            $this->response->data = new stdClass();
            if ($count === 0) {
                $this->response->data->records = NULL;
                $this->response->message = "There is no reservation that matched your search criteria";
            }
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listReservation);
            $this->response->data->records = $listReservation;
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
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
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
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    private function transformReservationList($reservations)
    {
        $reservations = $reservations->get();
        $listReservation = [];

        foreach($reservations as $reservation) {
            $reservationId = $reservation->brand_product_reservation_id;
            if (! isset($listReservation[$reservationId])) {
                $listReservation[$reservationId] = [
                    'brand_product_reservation_id' => $reservationId,
                    'store_name' => $reservation->store_name,
                    'created_at' => $reservation->created_at->format('Y-m-d H:i:s'),
                    'expired_at' => ! empty($reservation->expired_at)
                        ? $reservation->expired_at->format('Y-m-d H:i:s')
                        : null,
                    'status' => $reservation->status,
                    'items' => [],
                ];
            }

            $listReservation[$reservationId]['items'][] = [
                'brand_product_id' => $reservation->brand_product_id,
                'product_name' => $reservation->product_name,
                'image' => $reservation->image,
                'selling_price' => $reservation->selling_price,
            ];
        }

        return array_values($listReservation);
    }

}
