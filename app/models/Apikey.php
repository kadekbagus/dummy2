<?php

class Apikey extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $primaryKey = 'apikey_id';

    protected $table = 'apikeys';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    /**
     * Generate 40 random chars used for Api key.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param $user User - Instance of the User object
     * @return string
     */
    public static function genApiKey($user)
    {
        $chars  = strrev($user->email) . $user->user_id . $user->created_at;
        $chars .= microtime(TRUE);
        $chars .= uniqid();

        return sha1($chars);
    }

    /**
     * Generate 64 random chars used for Api secret key.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param $user User - Instance of the User object
     * @return string
     */
    public static function genSecretKey($user)
    {
        $chars = strrev($user->username) . $user->user_lastname . $user->user_firstname;
        $chars .= strrev($user->email) . $user->user_id . (string)$user->created_at;
        $chars .= microtime(TRUE) . $user->user_password;
        $chars .= uniqid() . mt_rand(0, 0xffffff);

        return hash('sha256', $chars);
    }
}
