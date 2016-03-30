<?php
/**
 * Model for table `campaign_account`.
 *
 * @author Irianto Pratama <irianto@dominopos.com>
 */
class CampaignAccount extends Eloquent
{
    protected $primaryKey = 'campaign_account_id';
    protected $table = 'campaign_account';

    public function parentCampaignAccount()
    {
        return $this->belongsTo('CampaignAccount', 'parent_user_id', 'user_id');
    }

    public function userMerchant()
    {
        return $this->hasMany('UserMerchant', 'user_id', 'user_id');
    }

    public function userMall()
    {
        return $this->userMerchant()->where('object_type', '=', 'mall');
    }

    public function userTenant()
    {
        return $this->userMerchant()->where('object_type', '=', 'tenant');
    }

    public function settings()
    {
        return $this->hasMany('Setting', 'object_id', 'user_id')->where('object_type', 'user');
    }
}