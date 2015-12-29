<?php
class CampaignGender extends Eloquent
{
    /**
     * CampaignGender Model
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'campaign_gender';

    protected $primaryKey = 'campaign_gender_id';

}