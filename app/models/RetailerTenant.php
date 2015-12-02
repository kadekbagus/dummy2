<?php

use OrbitRelation\BelongsToManyWithUUIDPivot;

class RetailerTenant extends Eloquent
{
    /**
     * RetailerTenant Model
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     */

    protected $primaryKey = 'retailer_tenant_id';

    protected $table = 'retailer_tenant';

    public function merchant()
    {
        return $this->belongTo('Merchant', 'merchant_id', 'retailer_id');
    }
}
