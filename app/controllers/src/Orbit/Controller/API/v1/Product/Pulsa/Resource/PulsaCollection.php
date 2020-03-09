<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Resource;

use Orbit\Helper\Resource\ResourceCollection;

/**
 * Resource Collection of pulsa.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class PulsaCollection extends ResourceCollection
{
    /**
     * Transform collection to array as response data.
     *
     * @return array
     */
    public function toArray()
    {
        foreach($this->collection as $item) {
            $this->data['records'][] = [
                'pulsa_item_id' => $item->pulsa_item_id,
                'pulsa_code' => $item->pulsa_code,
                'pulsa_display_name' => $item->pulsa_display_name,
                'object_type' => $item->object_type,
                'name' => $item->name,
                'is_promo' => $item->is_promo,
                'status' => $item->status,
                'quantity' => $item->quantity,
                'value' => $item->value,
                'vendor_price' => $item->vendor_price,
                'price' => $item->price,
                'created_at' => $item->created_at->toDateTimeString(),
                'updated_at' => $item->updated_at->toDateTimeString(),
            ];
        }

        return $this->data;
    }
}
