<?php

class PermissionRole extends Eloquent
{
    protected $primaryKey = 'permission_role_id';

    protected $table = 'permission_role';

    public function permission()
    {
        return $this->belongsTo('Permission', 'permission_id', 'permission_id');
    }

    public function role()
    {
        return $this->belongsTo('Role', 'role_id', 'role_id');
    }
}
