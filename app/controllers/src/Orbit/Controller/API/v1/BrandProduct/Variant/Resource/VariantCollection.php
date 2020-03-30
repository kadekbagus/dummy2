<?php

namespace Orbit\Controller\API\v1\BrandProduct\Variant\Resource;

use Orbit\Helper\Resource\EloquentResourceCollection;

/**
 * A collection of Variant.
 *
 * @author Budi <budi@gotomalls.com>
 */
class VariantCollection extends EloquentResourceCollection
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
                // 'id' => $item->variant_id,
                'name' => $item->variant_name,
                'options' => $this->transformOptions($item),
            ];
        }

        return $this->data;
    }

    protected function transformOptions($item)
    {
        return array_map(function($option) {
            return $option['value'];
        }, $item->options->toArray());
    }
}
