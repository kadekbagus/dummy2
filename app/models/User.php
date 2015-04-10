<?php

use Illuminate\Auth\UserTrait;
use Illuminate\Auth\UserInterface;

class User extends Eloquent implements UserInterface
{
    use UserTrait;
    use ModelStatusTrait;
    use UserRoleTrait;

    protected $primaryKey = 'user_id';

    protected $table = 'users';

    protected $hidden = array('user_password');

    public function role()
    {
        return $this->belongsTo('Role', 'user_role_id', 'role_id');
    }

    public function permissions()
    {
        return $this->belongsToMany('Permission', 'custom_permission', 'user_id', 'permission_id')->withPivot('allowed');
    }

    public function apikey()
    {
        return $this->hasOne('Apikey', 'user_id', 'user_id')->where('apikeys.status','=','active');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function userdetail()
    {
        return $this->hasOne('UserDetail', 'user_id', 'user_id');
    }

    public function getFullName()
    {
        return $this->user_firstname . ' ' . $this->user_lastname;
    }

    public function merchants()
    {
        return $this->hasMany('Merchant', 'user_id', 'user_id');
    }

    public function lastVisitedShop()
    {
        return $this->belongsTo('Retailer', 'user_details', 'user_id', 'last_visit_shop_id');
    }

    public function interests()
    {
        return $this->belongsToMany('PersonalInterest', 'user_personal_interest', 'user_id', 'personal_interest_id');
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

    /**
     * A user could be also mapped to an employee
     */
    public function employee()
    {
        return $this->hasOne('Employee', 'user_id', 'user_id');
    }

    /**
     * Tells Laravel the name of our password field so Laravel does not uses
     * its default `password` field. Our field name is `user_password`.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->user_password;
    }

    /**
     * Method to create api keys for current user.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return ApiKey Object
     */
    public function createAPiKey()
    {
        $apikey = new Apikey();
        $apikey->api_key = Apikey::genApiKey($this);
        $apikey->api_secret_key = Apikey::genSecretKey($this);
        $apikey->status = 'active';
        $apikey->user_id = $this->user_id;
        $apikey = $this->apikey()->save($apikey);

        return $apikey;
    }
}
