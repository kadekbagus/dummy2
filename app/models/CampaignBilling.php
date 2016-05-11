<?php

class CampaignBilling extends Eloquent
{
    /**
     * CampaignBilling Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'campaign_billings';

    protected $primaryKey = 'campaign_billing_id';

}