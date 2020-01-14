<?php

/**
 * Digital Product Model.
 */
class DigitalProduct extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'digital_products';

    protected $primaryKey = 'digital_product_id';

    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')->where('is_displayed', 'yes');
    }

    public function scopeIsPromo($query)
    {
        return $query->where('is_promo', 'yes');
    }
}
