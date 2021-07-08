<?php

namespace Orbit\Controller\API\v1\Pub\Cart\Resource;

/**
 * Brand product cart item mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait BrandProductCartItemResource
{
    protected function transformToBrandProductCartItem($item)
    {
        if (! isset($this->data['records']['items'][$item->cart_item_id])) {
            $this->data['records']['items'][$item->cart_item_id] = [
                'store_id' => $item->store_id,
                'cart_item_id' => $item->cart_item_id,
                'cart_item_status' => $item->status,
                'quantity' => $item->quantity,
                'product_type' => 'brand_product',
                'product_id' => $item->brand_product_id,
                'product_name' => $item->product_name,
                'product_status' => $item->product_status,
                'max_quantity' => $this->getMaxQuantity($item),
                'variant' => $item->variant,
                'image_url' => $item->image_url,
            ];
        }
    }

    protected function getMaxQuantity($item)
    {
        $remainingQuantity = $item->product_quantity
            - (int) $item->reserved_quantity
            - (int) $item->purchased_quantity;

        $remainingQuantity = $remainingQuantity < 0 ? 0 : $remainingQuantity;

        return $remainingQuantity;
    }
}
