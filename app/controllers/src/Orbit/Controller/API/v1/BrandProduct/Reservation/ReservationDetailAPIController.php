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
            $brandProductReservationId = OrbitInput::get('brand_product_reservation_id');

            if (! $this->isRoleAllowed()) {
                $this->response->code = 403;
                $this->response->status = 'error';
                $this->response->message = 'You are not allowed to access this resource.';
                $this->response->data = null;
                $httpCode = 403;

                return $this->render($httpCode);
            }

            $validator = Validator::make(
                array(
                    'reservation_id'      => $brandProductReservationId,
                ),
                array(
                    'reservation_id'      => 'required',
                ),
                array(
                    'reservation_id.required' => 'Reservation ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

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
                    CASE WHEN {$prefix}brand_product_reservations.expired_at < NOW()
                        THEN 'expired'
                        ELSE {$prefix}brand_product_reservations.status
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
                        $q->select('brand_product_variant_id', 'brand_product_id', 'sku', 'product_code');
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
                ->where('brand_product_reservations.brand_product_reservation_id', $brandProductReservationId)
                ->where(DB::raw("{$prefix}brand_product_reservations.brand_id"), $brandId)
                ->where('option_type', 'merchant');

            if (! empty($merchantId)) {
                $reservations = $reservations->where('brand_product_reservation_details.value', $merchantId);
            }

            $reservations = $reservations->firstOrFail();

            $returnedItem = new stdclass();
            if (is_object($reservations)) {
                $returnedItem->brand_product_reservation_id = $reservations->brand_product_reservation_id;
                $returnedItem->user_name = $reservations->users->user_firstname . ' ' . $reservations->users->user_lastname;
                $returnedItem->product_name = $reservations->product_name;
                $returnedItem->quantity = $reservations->quantity;
                $returnedItem->selling_price = $reservations->selling_price;
                $returnedItem->created_at = (string) $reservations->created_at;
                $returnedItem->expired_at = $reservations->expired_at;
                $returnedItem->status = $reservations->status;
                $imgPath = '';
                $cdnUrl = '';
                $sku = '';
                $barcode = '';
                if (is_object($reservations->brand_product_variant)) {
                    if (! empty($reservations->brand_product_variant->sku)) {
                        $sku = $reservations->brand_product_variant->sku;
                    }
                    if (! empty($reservations->brand_product_variant->product_code)) {
                        $barcode = $reservations->brand_product_variant->product_code;
                    }
                    if (is_object($reservations->brand_product_variant->brand_product)) {
                        if (! empty($reservations->brand_product_variant->brand_product->brand_product_main_photo)) {
                            if (is_object($reservations->brand_product_variant->brand_product->brand_product_main_photo[0])) {
                                $imgPath = $reservations->brand_product_variant->brand_product->brand_product_main_photo[0]->path;
                                $cdnUrl = $reservations->brand_product_variant->brand_product->brand_product_main_photo[0]->cdn_url;
                            }
                        }
                    }
                }

                $returnedItem->img_path = $imgPath;
                $returnedItem->cdn_url = $cdnUrl;
                $returnedItem->sku = $sku;
                $returnedItem->barcode = $barcode;
                $variants = [];
                foreach ($reservations->details as $variantDetail) {
                    $variants[] = $variantDetail->value;
                }
                $returnedItem->variants = implode(',', $variants);
            }

            $this->response->data = $returnedItem;
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

    /**
     * @return boolean
     */
    protected function isRoleAllowed($brandProductReservationId)
    {
        $user = App::make('currentUser');
        $brandId = $user->base_merchant_id;
        $userType = $user->user_type;
        $merchantId = $user->merchant_id;

        if ($userType === 'brand') {
            $brandProductReservation = BrandProductReservation::where('brand_product_reservation_id', $brandProductReservationId)
                ->where('brand_id', $brandId)
                ->first();

            if (is_object($brandProductReservation)) {
                return true;
            }

            return false;
        }

        if ($userType === 'store') {
            $brandProductReservation = BrandProductReservation::leftJoin('brand_product_reservation_details', 'brand_product_reservation_details.brand_product_reservation_id', '=', 'brand_product_reservations.brand_product_reservation_id')
                ->where('brand_product_reservation_id', $brandProductReservationId)
                ->where('brand_id', $brandId)
                ->where('brand_product_reservation_details.value', $merchantId)
                ->first();

            if (is_object($brandProductReservation)) {
                return true;
            }

            return false;
        }
    }

}
