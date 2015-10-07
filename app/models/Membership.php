<?php

class Membership extends Eloquent
{

    use GeneratedUuidTrait;

    /**
     * Membership Model
     *
     * @author Tian <tian@dominopos.com>
     */
    use ModelStatusTrait;

    protected $table = 'memberships';

    protected $primaryKey = 'membership_id';

    public function merchant()
    {
        return $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
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
