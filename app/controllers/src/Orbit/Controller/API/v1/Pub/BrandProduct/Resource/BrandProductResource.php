<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Resource;

use Orbit\Helper\Resource\Resource;

/**
 * Brand Product collection class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductResource extends Resource
{
    public function toArray()
    {
        return [
            'id' => $this->brand_product_id,
            'name' => $this->product_name,
            'description' => $this->product_description,
            'tnc' => $this->tnc,
            'status' => $this->status,
            'max_reservation_time' => $this->max_reservation_time,
            'brand_id' => $this->brand_id,
            'images' => $this->transformImages($this->resource)
        ];
    }

    protected function transformImages($item, $imagePrefix = '')
    {

    }
}
