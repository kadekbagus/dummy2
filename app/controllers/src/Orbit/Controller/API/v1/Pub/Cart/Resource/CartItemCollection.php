<?php

namespace Orbit\Controller\API\v1\Pub\Cart\Resource;

use Orbit\Helper\Resource\ResourceCollection;

/**
 * Cart item collection.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CartItemCollection extends ResourceCollection
{
    use BrandProductCartItemResource;

    public function toArray()
    {
        $this->data['records'] = ['stores' => [], 'items' => []];

        foreach($this->collection as $item) {

            if (! isset($this->data['records']['stores'][$item->store_id])) {
                $this->data['records']['stores'][$item->store_id] = [
                    'store_id' => $item->store_id,
                    'store_name' => $item->store_name,
                    'floor' => $item->floor,
                    'unit' => $item->unit,
                    'mall_id' => $item->mall_id,
                    'mall_name' => $item->mall_name,
                    'image_url' => $item->store_image_url,
                    'can_reserve' => $item->enable_reservation === 1,
                    'can_buy' => $item->enable_checkout === 1,
                ];
            }

            if (! empty($item->brand_product_variant_id)) {
                $this->transformToBrandProductCartItem($item);
            }

        }

        $this->data['returned_records'] = count($this->data['records']['items']);
        $this->data['records']['items'] = array_values($this->data['records']['items']);
        $this->data['records']['stores'] = array_values($this->data['records']['stores']);

        return $this->data;
    }
}
