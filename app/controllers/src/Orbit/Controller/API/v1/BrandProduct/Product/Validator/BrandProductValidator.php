<?php

namespace Orbit\Controller\API\v1\BrandProduct\Product\Validator;

use App;
use Media;
use Request;
use BaseStore;
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

                    if ($vo['option_type'] === 'variant_option') {
                        if (empty($vo['value'])) {
                            break;
                        }
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
            $originalPrice = 0.0;
            if (isset($pv['original_price'])) {
                $originalPrice = $pv['original_price'];
            }

            // If empty, assume valid (no need to compare)
            if (empty($originalPrice) || $originalPrice <= 0.0) {
                continue;
            }

            if ($pv['selling_price'] >= $originalPrice) {
                $valid = false;
                break;
            }
        }

        return $valid;
    }

    /**
     * Validate that brand product main photo is exists/sent if deleted_images
     * contains media_id of brand_product_main_photo.
     *
     * @param  [type] $attr      [description]
     * @param  [type] $value     [description]
     * @param  [type] $params    [description]
     * @param  [type] $validator [description]
     * @return [type]            [description]
     */
    public function mainPhoto($attr, $value, $params, $validator)
    {
        $valid = true;

        $deletedMedia = Media::select('media_name_id')
            ->whereIn('media_id', $value)->get();

        foreach($deletedMedia as $media) {
            if ($media->media_name_id === 'brand_product_main_photo') {
                if (! Request::hasFile('brand_product_main_photo')) {
                    $valid = false;
                    break;
                }
            }
        }

        return $valid;
    }

    /**
     * Validate that current user can create a product or not.
     *
     */
    public function canCreate($attrs, $value, $params)
    {
        return $this->requestedStoresBelongToUser();
    }

    /**
     * Validate that current user can update a given product.
     */
    public function canUpdate($attrs, $id, $params)
    {
        $valid = false;

        if (! App::bound('brandProduct')) {
            return false;
        }

        $brandProduct = App::make('brandProduct');

        // Only able to update product created by logged in user.
        if ($brandProduct->createdBy('me')) {
            $valid = true;
        }
        else {
            if (! $this->requestHasProductMainData()) {
                $valid = $this->requestedStoresBelongToUser();
            }
        }

        return $valid;
    }

    private function getStoresFromRequest()
    {
        $stores = [];

        if (App::bound('productVariants')) {
            $productVariants = App::make('productVariants');

            foreach($productVariants as $productVariant) {
                if (isset($productVariant['variant_options'])) {
                    foreach($productVariant['variant_options'] as $vo) {
                        if ($vo['option_type'] === 'merchant'
                            && ! empty($vo['value'])
                            && ! in_array($vo['value'], $stores)
                        ) {
                            $stores[] = $vo['value'];
                            break;
                        }
                    }
                }
            }
        }

        return $stores;
    }

    /**
     * Determine if current requested stores belong to user.
     *
     * @return bool
     */
    private function requestedStoresBelongToUser()
    {
        $valid = false;

        $user = App::make('currentUser');

        // Get store list from request
        $requestedStores = $this->getStoresFromRequest();

        if ($user->isAdmin()) {
            // Get list of stores belongs to this user.
            $userStores = BaseStore::select('base_store_id')
            ->where('base_merchant_id', $user->base_merchant_id)
            ->get()
            ->map(function($store) {
                return $store->base_store_id;
            })->toArray();
        }
        else if ($user->isStore()) {
            // Get list of stores belongs to this user.
            $user->load(['stores']);
            $userStores = $user->stores->map(function($store) {
                return $store->merchant_id;
            })->toArray();
        }

        if (empty($userStores)) {
            return false;
        }

        // If any requested store NOT in the list of user's stores,
        // then assume it is an invalid request (not authorized to create).
        foreach($requestedStores as $requestedStore) {
            if (! in_array($requestedStore, $userStores)) {
                $valid = false;
                break;
            }
            else {
                $valid = true;
            }
        }

        return $valid;
    }

    /**
     * Determine if request has product main data.
     *
     * @return bool
     */
    private function requestHasProductMainData()
    {
        return Request::has('product_name')
            || Request::has('product_description')
            || Request::has('tnc')
            || Request::has('max_reservation_time')
            || Request::has('status')
            || Request::has('category_id')
            || Request::hasFile('brand_product_main_photo');
    }
}
