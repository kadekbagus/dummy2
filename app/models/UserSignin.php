<?php

class UserSignin extends Eloquent
{
    /**
     * UserSignin Model
     *
     * @author kadek <kadek@dominopos.com>
     */

    protected $table = 'user_signin';

    protected $primaryKey = 'user_signin_id';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

}