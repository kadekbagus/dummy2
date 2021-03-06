<?php

/**
 * This links a Category with a specific Merchant (which is a Tenant of a Mall).
 * A single Tenant may be in multiple categories.
 *
 * The merchant_id in Category refers to its owning Mall.
 *
 */
class CategoryMerchant extends Eloquent
{
    /**
     * CategoryMerchant Model
     *
     */

    protected $table = 'category_merchant';

    protected $primaryKey = 'category_merchant_id';

    public function retailer()
    {
        return $this->belongsTo('Retailer', 'merchant_id', 'merchant_id')->isMall('no');
    }

    public function category()
    {
        return $this->belongsTo('Category', 'category_id', 'category_id');
    }
}
