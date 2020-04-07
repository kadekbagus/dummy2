<?php

namespace Orbit\Controller\API\v1\BrandProduct\Product\Validator;

use App;
use BrandProductVariant;

/**
 * Brand Product Validator.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductValidator
{
    /**
     * Validate that brand product variants has different sku each.
     *
     * @param  [type] $attr      [description]
     * @param  [type] $value     [description]
     * @param  [type] $params    [description]
     * @return [type]            [description]
     */
    public function uniqueSKU($attr, $value, $params)
    {
        $bpVariants = @json_decode($value);

        if (! $bpVariants) {
            return false;
        }

        $skus = [];
        foreach($bpVariants as $bpVariant) {
            $skus[] = $bpVariant->sku;
        }

        if (empty($skus)) {
            return false;
        }

        return BrandProductVariant::whereIn('sku', $skus)->first() === null;
    }

    /**
     * Validate that variants has at least one name and 1 option.
     *
     * @param  [type] $attr      [description]
     * @param  [type] $value     [description]
     * @param  [type] $params    [description]
     * @return [type]            [description]
     */
    public function variants($attr, $value, $params)
    {
        $variants = @json_decode($value, true);

        if (empty($variants)) {
            return false;
        }

        $valid = true;

        foreach($variants as $variant) {
            if (! isset($variant['name']) || ! isset($variant['options'])) {
                $valid = false;
                break;
            }

            if (isset($variant['name']) && empty($variant['name'])) {
                $valid = false;
                break;
            }

            if (isset($variant['options'])) {
                foreach($variant['options'] as $option) {
                    if (empty(trim($option))) {
                        $valid = false;
                        break;
                    }
                }
            }

            if (! $valid) {
                break;
            }
        }

        return $valid;
    }

    /**
     * Validate that brand_product_variants has at least one item with
     * selling_price and quantity must be numeric.
     *
     * @param  [type] $attr      [description]
     * @param  [type] $value     [description]
     * @param  [type] $params    [description]
     * @return [type]            [description]
     */
    public function productVariants($attr, $value, $params)
    {
        $productVariants = @json_decode($value, true);

        if (empty($productVariants)) {
            return false;
        }

        $valid = true;

        foreach($productVariants as $productVariant) {
            if (! isset($productVariant['selling_price'])
                || ! isset($productVariant['quantity'])
                || ! isset($productVariant['variant_options'])
            ) {
                $valid = false;
                break;
            }

            if ((
                    isset($productVariant['selling_price'])
                    && ! is_numeric($productVariant['selling_price'])
                )
                || (
                    isset($productVariant['quantity'])
                    && ! is_numeric($productVariant['quantity'])
                )
                || (
                    isset($productVariant['variant_options'])
                    && ! is_array($productVariant['variant_options'])
                )
            ) {
                $valid = false;
                break;
            }

            if (isset($productVariant['variant_options'])
                && is_array($productVariant['variant_options'])
            ) {
                $valid = false;
                foreach($productVariant['variant_options'] as $vo) {
                    if ($vo['option_type'] === 'merchant'
                        && ! empty($vo['value'])) {
                        $valid = true;
                        break;
                    }
                }

                if (! $valid) {
                    break;
                }
            }
        }

        if ($valid) {
            App::instance('productVariants', $productVariants);
        }

        return $valid;
    }

    /**
     * Validate that selling_price is lower than original_price.
     *
     * @param  [type] $attr   [description]
     * @param  [type] $value  [description]
     * @param  [type] $params [description]
     * @return [type]         [description]
     */
    public function sellingPriceLowerThanOriginalPrice($attr, $value, $params)
    {
        $productVariants = App::bound('productVariants')
            ? App::make('productVariants') : null;

        if (empty($productVariants)) {
            return false;
        }

        $valid = true;
        foreach($productVariants as $pv) {
            $originalPrice = 0;
            if (isset($pv['original_price'])) {
                $originalPrice = $pv['original_price'];
            }

            // If empty, assume valid (no need to compare)
            if (empty($originalPrice)) {
                continue;
            }

            if ($pv['selling_price'] >= $originalPrice) {
                $valid = false;
                break;
            }
        }

        return $valid;
    }
}