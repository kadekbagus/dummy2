<?php

/**
 * Eloquent model for extended_user table
 *
 * @author zamroni <zamroni@dominopos.com>
 */
class UserExtended extends Eloquent
{
    protected $primaryKey = 'extended_user_id';
    protected $table = 'extended_users';

    public function purchases()
    {
        return $this->hasMany('PaymentTransaction', 'user_id', 'user_id');
    }

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'user_id')
                    ->where('object_name', 'user');
    }

    public function profilePicture()
    {
        return $this->media()->where('media_name_id', 'user_profile_picture');
    }

    public function getNameAttribute($value)
    {
        return strip_tags($value);
    }

    public function getAboutAttribute($value)
    {
        return strip_tags($value);
    }

    public function getLocationAttribute($value)
    {
        return strip_tags($value);
    }
}
