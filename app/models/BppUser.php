<?php

class BppUser extends Eloquent
{
	use ModelStatusTrait;

    protected $primaryKey = 'bpp_user_id';

    protected $table = 'bpp_users';

    public function apikey()
    {
        return $this->hasOne('Apikey', 'user_id', 'bpp_user_id')->where('apikeys.status','=','active');
    }
}