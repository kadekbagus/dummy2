<?php

class UserDetail extends Eloquent
{
    protected $table = 'user_details';

    /**
     * Primary key
     *
     * @var string
     */
    protected $primaryKey = 'user_detail_id';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id')->where('users.status', '=', 'active');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function lastVisitedShop()
    {
        return $this->belongsTo('Retailer', 'last_visit_shop_id', 'merchant_id');
    }

    public function scopeActive($query)
    {
        return $query->join('users', 'user_details.user_id', '=', 'users.user_id')->where('users.status', '=', 'active');
    }

    public function merchant()
    {
        return $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
    }

    public function country()
    {
        return $this->belongsTo('Country', 'country_id', 'country_id');
    }
}
