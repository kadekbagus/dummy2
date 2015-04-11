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

    /**
     * Get only role ids by role names.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $rolename
     * @return array
     */
    public static function roleIdsByName($rolename=array())
    {
        return DB::table('roles')->whereIn('role_name', $rolename)->lists('role_id');
    }
}
