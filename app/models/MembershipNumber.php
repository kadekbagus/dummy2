<?php

class MembershipNumber extends Eloquent
{

    /**
     * MembershipNumber Model
     *
     * @author Tian <tian@dominopos.com>
     */
    use ModelStatusTrait;

    protected $table = 'membership_numbers';

    protected $primaryKey = 'membership_number_id';

    public function membership()
    {
        return $this->belongsTo('Membership', 'membership_id', 'membership_id');
    }

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function issuerMerchant()
    {
        return $this->belongsTo('Merchant', 'issuer_merchant_id', 'merchant_id');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    /**
     * Membership has many uploaded media.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'membership_id')
                    ->where('object_name', 'membership');
    }

}
