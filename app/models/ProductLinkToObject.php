<?php

class ProductLinkToObject extends Eloquent
{
    /**
    * Product Link To Object Model
    *
    * @author kadek <kadek@dominopos.com>
    *
    */

    protected $table = 'product_link_to_object';

    protected $primaryKey = 'product_link_to_object_id';

    public function brand()
    {
        return $this->hasOne('BaseMerchant', 'base_merchant_id', 'object_id');
    }
}