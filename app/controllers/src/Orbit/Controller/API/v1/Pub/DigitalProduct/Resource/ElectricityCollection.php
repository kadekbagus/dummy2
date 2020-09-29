<?php

namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Resource;

use Orbit\Helper\Resource\ResourceCollection;

/**
 * A collection of Electricity.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class ElectricityCollection extends ResourceCollection
{
    /**
     * Transform collection to array.
     *
     * @return array
     */
    public function toArray()
    {
        foreach($this->collection as $item) {
            $this->data['records'][] = [
                'digital_product_id' => $item->digital_product_id,
                'product_type' => $item->product_type,
                'product_name' => $item->product_name,
                'digital_product_code' => $item->digital_product_code,
                'status' => $item->status,
                'is_displayed' => $item->is_displayed,
                'is_promo' => $item->is_promo,
                'selling_price' => $item->selling_price,
                'provider_product_id' => $item->provider_product_id,
                'provider_name' => $item->provider_name,
                'provider_product_code' => $item->provider_product_code,
                'price' => $item->price,
                'extra_field_metadata' => $item->extra_field_metadata,
            ];
        }

        return $this->data;
    }
}
