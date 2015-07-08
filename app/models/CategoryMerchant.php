<?php

class CategoryMerchant extends Eloquent
{
    /**
     * CategoryMerchant Model
     *
     */

    protected $table = 'category_merchant';

    protected $primaryKey = 'category_merchant_id';

    public function mall()
    {
        return $this->belongsTo('Retailer', 'merchant_id', 'merchant_id')->isMall();
    }

    public function category()
    {
        return $this->belongsTo('Category', 'category_id', 'category_id');
    }
}
