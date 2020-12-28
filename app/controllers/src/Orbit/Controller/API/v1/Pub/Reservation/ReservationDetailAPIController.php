<?php

namespace Orbit\Controller\API\v1\Pub\Reservation;

use Exception;
use Illuminate\Support\Facades\App;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Reservation\Resource\ReservationResource;
use Orbit\Controller\API\v1\Pub\Reservation\Request\ReservationDetailRequest;

/**
 * Handle new reservation request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationDetailAPIController extends PubControllerAPI
{
    public function handle(ReservationDetailRequest $request)
    {
        try {
            // $this->enableQueryLog();

            $this->response->data = new ReservationResource(
                App::make('reservation')
            );

        } catch(Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
