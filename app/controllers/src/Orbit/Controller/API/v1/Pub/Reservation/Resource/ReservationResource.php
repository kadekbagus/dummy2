<?php

namespace Orbit\Controller\API\v1\Pub\Reservation\Resource;

use Orbit\Helper\Resource\Resource;
use Orbit\Controller\API\v1\Pub\BrandProduct\Resource\BrandProductReservationResource;
use ReflectionClass;

/**
 * Brand Product reservation resource class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationResource extends Resource
{
    protected $resourceMap = [
        'BrandProductReservation' => BrandProductReservationResource::class,
    ];

    public function toArray()
    {
        $className = (new ReflectionClass($this->resource))->getShortName();
        return (new $this->resourceMap[$className]($this->resource))->toArray();
    }
}
