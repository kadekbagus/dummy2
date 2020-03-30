<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Resource;

use Orbit\Helper\Resource\Resource;

/**
 * Brand Product reservation resource class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ProductReservationResource extends Resource
{
    public function toArray()
    {
        return [
            'id' => $this->brand_product_id,
            'status' => $this->status,
            'expired_at' => $this->expired_at,
        ];
    }
}
