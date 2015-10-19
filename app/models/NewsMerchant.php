<?php

class NewsMerchant extends Eloquent
{
    /**
     * NewsMerchant Model
     *
     * @author Tian <tian@dominopos.com>
     */

    protected $table = 'news_merchant';

    protected $primaryKey = 'news_merchant_id';

    public $timestamps = false;

    public function news()
    {
        return $this->belongsTo('News', 'news_id', 'news_id');
    }

    public function tenant()
    {
        return $this->belongsTo('Tenant', 'merchant_id', 'merchant_id');
    }
}
