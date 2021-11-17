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

            if (! $this->isRoleAllowed($brandProductReservationId)) {
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
                    {$prefix}brand_product_reservation_details.selling_price,
                    {$prefix}brand_product_reservations.created_at,
                    {$prefix}brand_product_reservations.updated_at,
                    {$prefix}brand_product_reservations.merchant_id,
                    {$prefix}brand_product_reservations.expired_at,
                    {$prefix}brand_product_reservation_details.quantity,
                    {$prefix}brand_product_reservations.user_id,
                    {$prefix}brand_product_reservations.total_amount,
                    {$prefix}brand_product_reservation_details.product_name,
                    {$prefix}brand_product_reservation_details.product_name,
                    {$prefix}brand_product_reservation_details.brand_product_variant_id,
                    {$prefix}brand_product_reservations.cancel_reason,
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
                    'store.mall',
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
                ->where('brand_product_reservations.brand_product_reservation_id', $brandProductReservationId);

            ($userType === 'gtm_admin') ? null : $reservations->where(DB::raw("{$prefix}brand_product_reservations.brand_id"), $brandId);
            isset($merchantId) ? $reservations->where('brand_product_reservations.merchant_id', '=', $merchantId) : null;

            $reservations = $reservations->firstOrFail();

            $returnedItem = new stdclass();
            if (is_object($reservations)) {
                $returnedItem->brand_product_reservation_id = $reservations->brand_product_reservation_id;
                $returnedItem->user_id = $reservations->users->user_id;
                $returnedItem->user_name = $reservations->users->user_firstname . ' ' . $reservations->users->user_lastname;
                $returnedItem->created_at = (string) $reservations->created_at;
                $returnedItem->updated_at = (string) $reservations->updated_at;
                $returnedItem->expired_at = (string) $reservations->expired_at;
                $returnedItem->status = $reservations->status;
                $returnedItem->cancel_reason = $reservations->cancel_reason;
                $returnedItem->total_amount = $reservations->total_amount;
                $storeName = '';
                $mallName = '';
                if (is_object($reservations->store)) {
                    if (! empty($reservations->store->name)) {
                        $storeName = $reservations->store->name;
                    }
                    if (is_object($reservations->store->mall)) {
                        if (! empty($reservations->store->mall->name)) {
                            $mallName = $reservations->store->mall->name;
                        }
                    }
                }
                $returnedItem->pick_up_location = '';
                if (!empty($storeName) && !empty($mallName)) {
                    $returnedItem->pick_up_location = $storeName . ' at ' . $mallName;
                }

                foreach ($reservations->details as $detail) {
                    $dtl = new stdclass();

                    $dtl->product_name = $detail->product_name;
                    $dtl->quantity = $detail->quantity;
                    $dtl->selling_price = $detail->selling_price;
                    $dtl->original_price = $detail->original_price;
                    $dtl->image_url = $detail->image_url;
                    $dtl->image_cdn = $detail->image_cdn;
                    $sku = '';
                    $barcode = '';
                    if (! empty($detail->sku)) {
                        $sku = $detail->sku;
                    }
                    if (! empty($detail->product_code)) {
                        $barcode = $detail->product_code;
                    }

                    $dtl->sku = $sku;
                    $dtl->barcode = $barcode;

                    $variants = [];
                    foreach ($detail->variant_details as $variantDetail) {
                        $variants[] = strtoupper($variantDetail->value);
                    }
                    $dtl->variants = implode(', ', $variants);
                    $returnedItem->details[] = $dtl;
                }
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
            $brandProductReservation = BrandProductReservation::where('brand_product_reservation_id', $brandProductReservationId)
                ->where('brand_id', $brandId)
                ->where('merchant_id', $merchantId)
                ->first();

            if (is_object($brandProductReservation)) {
                return true;
            }

            return false;
        }

        if ($userType === 'gtm_admin') {
            return true;
        }
    }

}
