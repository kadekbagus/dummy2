<?php

namespace Orbit\Controller\API\v1\Pub\Cart\Resource;

use CartItem;

/**
 * Brand product cart item mapper.
 */
trait BrandProductCartItemResource
{
    protected function parseBrandProductInfo($item)
    {
        return [
            // 'pickup_location' => $this->getPickupLocation($item),
            // 'brand_name' => $this->getBrandName($item),
            'image_url' => $this->transformImages($item),
            'original_price' => $item->original_price,
            'selling_price' => $item->selling_price,
            'product_info' => $item->product_name,
            'product_type' => 'brand_product',
            'product_id' => $item->brand_product_id,
            'status' => ! empty($item->brand_product_id)
                ? $item->status : CartItem::STATUS_NOT_FOUND
        ];
    }
}
