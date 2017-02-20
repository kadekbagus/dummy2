<?php
/**
 * Model for table `campaign_account`.
 *
 * @author Irianto Pratama <irianto@dominopos.com>
 */
class CampaignAccount extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

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
        return $this->userMerchant()->where('user_merchant.object_type', '=', 'mall');
    }

    public function userTenant()
    {
        return $this->userMerchant()->where('user_merchant.object_type', '=', 'tenant');
    }


    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function userdetail()
    {
        return $this->hasOne('UserDetail', 'user_id', 'user_id');
    }

    public function accountType()
    {
        return $this->belongsTo('AccountType', 'account_type_id', 'account_type_id');
    }

    public function settings()
    {
        return $this->hasMany('Setting', 'object_id', 'user_id')->where('object_type', 'user');
    }

    public function language()
    {
        return $this->hasMany('ObjectSupportedLanguage', 'object_id', 'campaign_account_id')->excludeDeleted('object_supported_language')->where('object_type', 'pmp_account');
    }

    public function pmpLanguages()
    {
        return $this->language()->join('languages', 'languages.language_id', '=', 'object_supported_language.language_id')->select('languages.language_id', 'name', 'name_long', 'name_native');
    }
}