<?php

namespace Orbit\Controller\API\v1\Pub\Cart\Resource;

use Orbit\Helper\Resource\Resource;

/**
 * Cart item resource.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CartItemResource extends Resource
{
    public function toArray()
    {
        return [
            'cart_item_id' => $this->cart_item_id,
            'product_image' => $this->transformImages(),
            'quantity' => $this->quantity,
            'status' => $this->status,
            'original_price' => $this->original_price,
            'selling_price' => $this->selling_price,
        ];
    }

    protected function transformImages()
    {
        return '';
    }
}
