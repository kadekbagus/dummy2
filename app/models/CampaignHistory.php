<?php

class CampaignHistory extends Eloquent
{
    /**
     * CampaignHistory Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'campaign_histories';

    protected $primaryKey = 'campaign_history_id';

}