<?php

use Orbit\Helper\Resource\MediaQuery;

/**
 * Cart Item model.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CartItem extends Eloquent
{
    use ModelStatusTrait,
        MediaQuery;

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'deleted';

    protected $guarded = [];

    protected $primaryKey = 'cart_item_id';

    protected $table = 'cart_items';

    public function brand_product_variant()
    {
        return $this->belongsTo(BrandProductVariant::class);
    }
}
