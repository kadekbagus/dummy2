<?php

namespace Orbit\Controller\API\v1\Pub\Reservation\Resource;

use BrandProductReservation;
use Orbit\Helper\Resource\Resource;
use Orbit\Controller\API\v1\Pub\BrandProduct\Resource\BrandProductReservationResource;

/**
 * Brand Product reservation resource class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationResource extends Resource
{
    public function toArray()
    {
        if ($this->resource instanceof BrandProductReservation) {
            return (new BrandProductReservationResource($this->resource))
                ->toArray();
        }
    }
}
