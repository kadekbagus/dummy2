<?php

namespace Orbit\Controller\API\v1\Pub\Reservation;

use App;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Reservation\Request\ReservationPickedUpRequest;
use Orbit\Controller\API\v1\Pub\Reservation\Resource\ReservationResource;
use Orbit\Controller\API\v1\Reservation\ReservationInterface;

/**
 * Reservation purchase status update.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationPickedUpAPIController extends PubControllerAPI
{
    /**
     * Handle order update status.
     *
     * @param  OrderStatusUpdateRequest $request [description]
     * @return [type]                            [description]
     */
    function handle(
        ReservationPickedUpRequest $request,
        ReservationInterface $reservation
    ) {
        try {

            $this->beginTransaction();

            $this->response->data = new ReservationResource(
                $reservation->pickedUp(App::make('reservation'))
            );

            $this->commit();

        } catch (Exception $e) {
            return $this->handleException($e);
        }
        return $this->render();
    }
}
