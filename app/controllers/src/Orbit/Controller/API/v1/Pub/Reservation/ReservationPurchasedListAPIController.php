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

            $reservation = BrandProductReservation::with([
                    'users' => function($q) {
                        $q->select('users.user_id', 'user_firstname', 'user_lastname');
                    },
                    'store.mall',
                    'details' => function($q) {
                        $q->with([
                            'product_variant' => function($q1) {
                                $q1->with([
                                    'brand_product' => function($q3) {
                                        $q3->select('brand_product_id');
                                        $q3->with([
                                            'brand_product_main_photo' => function($q4) {
                                                $q4->select('media_id', 'object_id', 'path', 'cdn_url');
                                            }
                                        ]);
                                    }
                                ]);
                            },
                            'variant_details' => function($q12) {
                                $q12->select('brand_product_reservation_detail_id', 'brand_product_reservation_variant_detail_id', 'value')
                                    ->where('option_type', 'variant_option');
                            }
                        ]);
                    }
                ])
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
            $reservationItem = new \stdClass();
            $reservationItem->brand_product_reservation_id = $reservation->brand_product_reservation_id;
            $reservationItem->store_name = $reservation->store->name . ' @ ' . $reservation->store->mall->name;
            $reservationItem->status = $reservation->status;
            $reservationItem->created_at = $reservation->created_at->format('Y-m-d H:i:s');
            $reservationItem->expired_at = ! empty($reservation->expired_at)
                        ? $reservation->expired_at->format('Y-m-d H:i:s')
                        : null;
            $reservationItem->products = [];

            foreach ($reservation->details as $detail) {
                $dtl = new stdclass();

                $dtl->product_name = $detail->product_name;
                $dtl->quantity = $detail->quantity;
                $dtl->selling_price = $detail->selling_price;
                $dtl->original_price = $detail->original_price;
                $imgPath = '';
                $cdnUrl = '';
                if (is_object($detail->product_variant)) {
                    if (is_object($detail->product_variant->brand_product)) {
                        if (! empty($detail->product_variant->brand_product->brand_product_main_photo)) {
                            if (is_object($detail->product_variant->brand_product->brand_product_main_photo[0])) {
                                $imgPath = $detail->product_variant->brand_product->brand_product_main_photo[0]->path;
                                $cdnUrl = $detail->product_variant->brand_product->brand_product_main_photo[0]->cdn_url;
                            }
                        }
                    }
                }

                $dtl->img_path = $imgPath;
                $dtl->cdn_url = $cdnUrl;

                $variants = [];
                foreach ($detail->variant_details as $variantDetail) {
                    $variants[] = strtoupper($variantDetail->value);
                }
                $dtl->variants = implode(', ', $variants);
                $reservationItem->products[] = $dtl;
            }
            $listReservation[] = $reservationItem;
        }
        return $listReservation;
    }

}
