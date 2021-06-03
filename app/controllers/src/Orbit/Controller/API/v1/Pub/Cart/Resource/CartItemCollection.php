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
        foreach($this->collection as $item) {
            $this->data['records'] = [
                'cart_item_id' => $item->cart_item_id,
                'quantity' => $item->quantity,
                'status' => $item->status,
            ];

            if (! empty($item->brand_product_variant)) {
                $this->data['records'] += $this->parseBrandProductInfo($item);
            }
        }
    }
}
