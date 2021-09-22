<?php

namespace Orbit\Controller\API\v1\BrandProduct\Reservation;

use DB;
use App;
use Lang;
use Config;
use stdclass;
use Exception;
use Validator;
use Carbon\Carbon;
use DominoPOS\OrbitACL\ACL;
use BrandProductVariant;
use BrandProductReservation;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\ControllerAPI;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\QueryException;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;

class ReservationUpdateStatusAPIController extends ControllerAPI
{

    /**
     * Decline or accept brand product reservation
     *
     * @author Kadek <kadek@dominopos.com>
     */
    public function postUpdate()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $userType = $user->user_type;
            $brandId = $user->base_merchant_id;
            $merchantId = $user->merchant_id;
            $brandProductReservationId = OrbitInput::post('brand_product_reservation_id');
            $status = OrbitInput::post('status');
            $cancelReason = OrbitInput::post('cancel_reason', 'Out of Stock');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'reservation_id'      => $brandProductReservationId,
                    'status'              => $status,
                ),
                array(
                    'reservation_id'      => 'required|orbit.reservation.exists:'.$brandId,
                    'status'              => 'required|in:'. join(',', [
                            BrandProductReservation::STATUS_ACCEPTED,
                            BrandProductReservation::STATUS_DECLINED,
                            BrandProductReservation::STATUS_DONE,
                        ]),
                ),
                array(
                    'orbit.reservation.exists' => 'Reservation not found',
                    'reservation_id.required' => 'Reservation ID is required',
                    'status.in' => 'available status are: '.BrandProductReservation::STATUS_ACCEPTED.','.BrandProductReservation::STATUS_DECLINED
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $reservation = App::make('orbit.reservation.exists');

            if ($reservation->status === BrandProductReservation::STATUS_DONE) {
                $errorMessage = 'Reservation already done';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if ($reservation->status === BrandProductReservation::STATUS_EXPIRED) {
                $errorMessage = 'Reservation is expired';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if ($reservation->status === BrandProductReservation::STATUS_ACCEPTED
                && $status === BrandProductReservation::STATUS_ACCEPTED
            ) {
                $errorMessage = 'Reservation already accepted';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if ($reservation->status === BrandProductReservation::STATUS_DECLINED) {
                $errorMessage = 'Reservation already declined';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $previousStatus = $reservation->status;
            $reservation->status = $status;

            if ($status === BrandProductReservation::STATUS_DECLINED) {
                $reservation->declined_by = $userId;
                $reservation->cancel_reason = $cancelReason;

                // update stock if previous status is accepted
                if ($previousStatus === BrandProductReservation::STATUS_ACCEPTED) {
                    $reservation->load(['details']);
                    foreach ($reservation->details as $detail) {
                        $detail->load(['product_variant']);
                        $updateStock = BrandProductVariant::where('brand_product_variant_id', '=', $detail->brand_product_variant_id)->first();
                        if ($updateStock) {
                            $updateStock->quantity = $detail->product_variant->quantity + $detail->quantity;
                            $updateStock->save();
                        }
                    }
                }
                
            }

            if ($status === BrandProductReservation::STATUS_ACCEPTED) {
                $reservation->load(['details']);
                $max_reservation_time = 0;
                foreach ($reservation->details as $detail) {
                    $detail->load(['product_variant.brand_product']);

                    if (!is_object($detail->product_variant)) {
                        OrbitShopAPI::throwInvalidArgument('Change status failed! Unable to find linked product variant for one or more of the product in this reservation. Variant might be changed or deleted.');
                    }

                    if (!is_object($detail->product_variant->brand_product)) {
                        OrbitShopAPI::throwInvalidArgument('Change status failed! Unable to find linked brand product for one or more of the product in this reservation. It might be changed or deleted.');
                    }

                    // take the longest max reservation time from each products
                    $max_reservation_time = $max_reservation_time <= $detail->product_variant->brand_product->max_reservation_time ? $detail->product_variant->brand_product->max_reservation_time : $max_reservation_time;

                    // update stock
                    $updateStock = BrandProductVariant::where('brand_product_variant_id', '=', $detail->brand_product_variant_id)->first();
					if ($updateStock) {
						$updateStock->quantity = $detail->product_variant->quantity - $detail->quantity;
						$updateStock->save();
					}
                }

                $reservation->expired_at = Carbon::now()->addMinutes($max_reservation_time);
            }

            if ($status === BrandProductReservation::STATUS_DONE) {
                $reservation->status = 'done';
            }

            $reservation->save();

            // Commit the changes
            $this->commit();

            if ($status === BrandProductReservation::STATUS_ACCEPTED) {
                Event::fire('orbit.reservation.accepted', [$reservation]);
            }
            else if ($status === BrandProductReservation::STATUS_DECLINED) {
                Event::fire('orbit.reservation.declined', [$reservation]);
            }
            else if ($status === BrandProductReservation::STATUS_DONE) {
                Event::fire('orbit.reservation.done', [$reservation]);
            }

            $reservation = BrandProductReservation::with(['details.product_variant'])  
                                                    ->where('brand_product_reservation_id', $brandProductReservationId)
                                                    ->where('brand_id', '=', $brandId)
                                                    ->first();

            $this->response->data = $reservation;
        } catch (ACLForbiddenException $e) {
            // Rollback the changes
            $this->rollBack();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            // Rollback the changes
            $this->rollBack();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            // Rollback the changes
            $this->rollBack();
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
            // Rollback the changes
            $this->rollBack();
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check the existance of reservation
        Validator::extend('orbit.reservation.exists', function ($attribute, $value, $parameters) {
            $brandId = $parameters[0];
            $prefix = DB::getTablePrefix();

            $reservation = BrandProductReservation::where('brand_product_reservation_id', $value)
                                                    ->where('brand_id', '=', $brandId)
                                                    ->first();

            if (empty($reservation)) {
                return FALSE;
            }

            App::instance('orbit.reservation.exists', $reservation);

            return TRUE;
        });
    }
}
