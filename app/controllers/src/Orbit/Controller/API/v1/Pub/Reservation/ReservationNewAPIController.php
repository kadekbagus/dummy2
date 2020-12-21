<?php

namespace Orbit\Controller\API\v1\Pub\Reservation;

use Exception;
use Illuminate\Support\Facades\App;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Reservation\ReservationInterface;
use Orbit\Controller\API\v1\Pub\Reservation\Request\MakeReservationRequest;

/**
 * Handle new reservation request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationNewAPIController extends PubControllerAPI
{
    public function handle(MakeReservationRequest $request, ReservationInterface $reservation)
    {
        try {
            // $this->enableQueryLog();

            $this->response->data = $reservation->make(
                App::make('productVariant'),
                $request
            );

        } catch(Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
