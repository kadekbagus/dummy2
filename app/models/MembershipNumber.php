<?php

class MembershipNumber extends Eloquent
{

    use GeneratedUuidTrait;

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

}
