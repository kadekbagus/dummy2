<?php
class CampaignAge extends Eloquent
{
    /**
     * CampaignAge Model
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'campaign_age';

    protected $primaryKey = 'campaign_age_id';

    public function ageRange()
    {
        return $this->hasOne('AgeRange', 'age_range_id', 'age_range_id');
    }

}