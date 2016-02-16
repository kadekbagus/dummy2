<?php

class CampaignBasePrice extends Eloquent
{
    /**
     * CampaignBasePrice Model
     *
     * @author Shelgi <shelgi@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'campaign_base_prices';

    protected $primaryKey = 'campaign_base_price_id';

    public function merchant()
    {
        return $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
    }

    public function scopeOfMallAndType($query, $mallId, $type)
    {
        return $query->whereMerchantId($mallId)->whereCampaignType($type);
    }

}