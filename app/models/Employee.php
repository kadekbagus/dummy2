<?php
/**
 * Employee class for represent the structure of employees table.
 *
 * @author Rio Astamal <me@rioastamal.net?
 */
class Employee extends Eloquent
{
    protected $table = 'employees';
    protected $primaryKey = 'employee_id';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Employee belongs to a User
     */
    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    /**
     * Employee could belongs to and has many retailers
     */
    public function retailers()
    {
        return $this->belongsToMany('Retailer', 'employee_retailer', 'employee_id', 'retailer_id');
    }

    /**
     * Scope to join with user table.
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function scopeJoinUser()
    {
        return $this->select('employees.*')
                    ->join('users', 'users.user_id', '=', 'employees.user_id');
    }

    /**
     * Scope to join with user table.
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function scopeJoinUserRole($query)
    {
        return $query->join('users', 'users.user_id', '=', 'employees.user_id')
                     ->join('roles', 'roles.role_id', '=', 'users.user_role_id');
    }

    /**
     * Employee belongs to many merchant ids.
     *
     * @return
     */
    public function getMyMerchantIds()
    {
        $empId = $this->employee_id;
        $prefix = DB::getTablePrefix();

        return DB::table('merchants')->whereRaw("merchant_id IN (SELECT `retailer_id`
                                                 from {$prefix}employee_retailer where `employee_id`=?)", [$empId])
                 ->where('object_type', 'retailer')
                 ->groupBy('parent_id')
                 ->lists('parent_id');
    }
}
