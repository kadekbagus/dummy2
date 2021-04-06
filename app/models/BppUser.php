<?php

class BppUser extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'bpp_user_id';

    protected $table = 'bpp_users';

    protected $hidden = array('password', 'apikey', 'api_key');

    public function apikey()
    {
        return $this->hasOne('Apikey', 'user_id', 'bpp_user_id')->where('apikeys.status','=','active');
    }

    public function createAPiKey($apiKeyId = null)
    {
        $apikey = new Apikey();
        if (isset($apiKeyId)) {
            $apikey->apikey_id = $apiKeyId;
        }
        $apikey->api_key = Apikey::genApiKey($this);
        $apikey->api_secret_key = Apikey::genSecretKey($this);
        $apikey->status = 'active';
        $apikey->user_id = $this->user_id;
        $apikey = $this->apikey()->save($apikey);

        return $apikey;
    }

    public function stores()
    {
        return $this->belongsToMany(Tenant::class, 'bpp_user_merchants', 'bpp_user_id', 'merchant_id', null, 'bpp_user_merchant_id')
            ->withTimestamps();
    }

    public function isAdmin()
    {
        return $this->user_type === 'brand';
    }

    public function isStore()
    {
        return $this->user_type === 'store';
    }
}
