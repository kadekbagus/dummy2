<?php

namespace Orbit\Controller\API\v1\BrandProduct\Store\Resource;

use Orbit\Helper\Resource\EloquentResourceCollection;

/**
 * A collection of Store.
 *
 * @author Budi <budi@gotomalls.com>
 */
class StoreCollection extends EloquentResourceCollection
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
                'id' => $item->merchant_id,
                'name' => $item->store_name,
                'mall_name' => $item->mall_name,
                'location' => $this->transformLocation($item),
            ];
        }

        return $this->data;
    }

    protected function transformLocation($item)
    {
        return "{$item->floor} {$item->unit}";
    }
}
