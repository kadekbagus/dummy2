<?php

class Permission extends Eloquent
{
    protected $primaryKey = 'permission_id';

    protected $table = 'permissions';

    public function users()
    {
        return $this->belongsToMany('User', 'custom_permission', 'permission_id', 'user_id')->withPivot('allowed');
    }

    public function roles()
    {
        return $this->belongsToMany('Role', 'permission_role', 'permission_id', 'role_id')->withPivot('allowed');
    }
}
