<?php

class MembershipNumber extends Eloquent
{

    /**
     * MembershipNumber Model
     *
     * @author Tian <tian@dominopos.com>
     * @author Rio Astamal <rio@dominopos.com>
     */
    use ModelStatusTrait;

    protected $table = 'membership_numbers';

    protected $primaryKey = 'membership_number_id';

    const STATUS_ACTIVE = 'active';
    const STATUS_NOTACTIVE = 'inactive';

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

    public function mall()
    {
        return $this->belongsTo('Mall', 'issuer_merchant_id', 'merchant_id');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

}