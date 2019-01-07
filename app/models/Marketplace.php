<?php

class Marketplace extends Eloquent
{
    /**
    * MarketPlace Model
    *
    * @author kadek <kadek@dominopos.com>
    *
    */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'marketplaces';

    protected $primaryKey = 'marketplace_id';

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'marketplace_id')
                    ->where('object_name', 'marketplace');
    }
}
