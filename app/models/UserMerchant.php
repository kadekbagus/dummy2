<?php

class UserMerchant extends Eloquent
{
    protected $primaryKey = 'user_merchant_id';
    protected $table = 'user_merchant';

    public function merchant()
    {
        return $this->belongsTo('Merchant');
    }

    public function mall()
    {
        return $this->hasMany('Mall', 'merchant_id', 'merchant_id');
    }

    public function tenant()
    {
        return $this->belongsTo('Tenant', 'merchant_id', 'merchant_id');
    }
}
