<?php

namespace Orbit\Controller\API\v1\Pub\Reservation;

use Exception;
use Illuminate\Support\Facades\App;
use Orbit\Controller\API\v1\Pub\Reservation\Request\CancelReservationRequest;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Reservation\ReservationInterface;

/**
 * Handle new reservation request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationCancelAPIController extends PubControllerAPI
{
    public function handle(CancelReservationRequest $request, ReservationInterface $reservation)
    {
        try {
            // $this->enableQueryLog();

            $this->response->data = $reservation->cancel(
                App::make('reservation')
            );

        } catch(Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
