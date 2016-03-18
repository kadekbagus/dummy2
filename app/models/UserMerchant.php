<?php

class UserMerchant extends Eloquent
{
    protected $primaryKey = 'user_merchant_id';
    protected $table = 'user_merchant';

    public function merchant()
    {
        return $this->belongsTo('Merchant');
    }
}
