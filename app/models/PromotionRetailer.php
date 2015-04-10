<?php

class PromotionRetailer extends Eloquent
{
    /**
     * PromotionRetailer Model
     *
     * @author Tian <tian@dominopos.com>
     */

    protected $primaryKey = 'promotion_retailer_id';

    protected $table = 'promotion_retailer';

    public function promotion()
    {
        return $this->belongsTo('Promotion', 'promotion_id', 'promotion_id');
    }

    public function retailer()
    {
        return $this->belongsTo('Retailer', 'retailer_id', 'merchant_id');
    }
}
