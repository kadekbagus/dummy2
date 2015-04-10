<?php

class Role extends Eloquent
{
    protected $primaryKey = 'role_id';

    protected $table = 'roles';

    public function users()
    {
        return $this->hasMany('User', 'user_role_id', 'role_id');
    }

    public function permissions()
    {
        return $this->belongsToMany('Permission', 'permission_role', 'role_id', 'permission_id')->withPivot('allowed');
    }
}
