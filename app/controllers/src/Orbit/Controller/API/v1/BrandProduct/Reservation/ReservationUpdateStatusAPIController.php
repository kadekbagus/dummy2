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
use stdclass;

use DB;
use Lang;
use Config;
use BrandProductReservation;
use Exception;
use App;
use Illuminate\Support\Facades\Event;

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

            $reservation->status = $status;

            if ($status === BrandProductReservation::STATUS_DECLINED) {
                $reservation->declined_by = $userId;
                $reservation->cancel_reason = $cancelReason;
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

            $reservation = BrandProductReservation::where('brand_product_reservation_id', $value)->where('brand_id', '=', $brandId)->first();

            if (empty($reservation)) {
                return FALSE;
            }

            App::instance('orbit.reservation.exists', $reservation);

            return TRUE;
        });
    }
}
