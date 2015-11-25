<?php

class UserVerificationNumber extends Eloquent
{
    protected $table = 'user_verification_numbers';

    /**
     * Primary key
     *
     * @var string
     */
    protected $primaryKey = 'user_verification_number_id';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id')->where('users.status', '=', 'active');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function merchant()
    {
        return $this->belongsTo('MallGroup', 'merchant_id', 'merchant_id');
    }

    public function retailer()
    {
        return $this->belongsTo('Mall', 'retailer_id', 'merchant_id');
    }

}
