<?php

class Product extends Eloquent
{
    /**
    * Product Model
    *
    * @author kadek <kadek@dominopos.com>
    *
    */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'products';

    protected $primaryKey = 'product_id';

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'product_id')
                    ->where('object_name', 'product');
    }
}
