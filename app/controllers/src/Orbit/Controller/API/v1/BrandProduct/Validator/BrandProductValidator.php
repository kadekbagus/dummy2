<?php

namespace Orbit\Controller\API\v1\BrandProduct\Validator;

use App;
use BrandProduct;
use BrandProductVariant;
use BrandProductReservation;

/**
 * Brand Product Validator.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductValidator
{
    public function exists($attribute, $productId, $params, $validator)
    {
        $brandProduct = BrandProduct::where('brand_product_id', $productId)
                            ->first();

        if (! empty($brandProduct)) {
            App::instance('brandProduct', $brandProduct);
        }

        return ! empty($brandProduct);
    }

    public function variant_exists($attribute, $variantId, $params)
    {
        $variant = $this->getVariant($variantId);

        return ! empty($variant);
    }

    public function product_exists($attribute, $value, $params)
    {
        $variant = $this->getVariant($value);

        return ! empty($variant) && ! empty($variant->brand_product);
    }

    /**
     * This method assumes brand product variant is available inside container.
     */
    public function quantity_available($attrs, $value, $params)
    {
        $variant = $this->getVariant();

        if ($variant->quantity === 0) {
            return true;
        }

        $usedQuantity = BrandProductReservation::select('quantity')
            ->where('brand_product_variant_id', $variant->brand_product_variant_id)
            ->whereIn('status', [
                BrandProductReservation::STATUS_NEW,
                BrandProductReservation::STATUS_DONE
            ])
            ->sum('quantity');

        return $variant->quantity - $usedQuantity >= $value;
    }

    private function getVariant($variantId = '')
    {
        if (App::bound('productVariant')) {
            return App::make('productVariant');
        }

        $variant = BrandProductVariant::with([
            'brand_product',
            'variant_options.variant_option',
            'variant_options.option',
        ])->where('brand_product_variant_id', $variantId)->first();

        App::instance('productVariant', $variant);

        return $variant;
    }
}
