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

    public function userdetail()
    {
        return $this->hasOne('UserDetail', 'user_id', 'user_id');
    }

    public function userVerificationNumber()
    {
        return $this->hasOne('UserVerificationNumber', 'user_id', 'user_id');
    }

    /**
     * Employee could belongs to and has many retailers
     */
    public function retailers()
    {
        return $this->belongsToMany('Mall', 'employee_retailer', 'employee_id', 'retailer_id');
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

    /**
     * Scope to get list of mall employees (customer service) for particular coupon
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param Builder $builder Query Builder
     * @param string $couponId Coupon ID
     * @param Builder $builder Query Builder
     */
    public function scopeByCouponId($builder, $couponId)
    {
        $prefix = DB::getTablePrefix();
        $couponId = DB::connection()->getPdo()->quote($couponId);

        $builder->addSelect('employee_retailer.retailer_id as mall_id', 'users.user_firstname', 'users.user_lastname', 'employees.*')
                ->join('employee_retailer', 'employee_retailer.employee_id', '=', 'employees.employee_id')
                ->join('users', function($q) {
                    $q->on('users.user_id', '=', 'employees.user_id');
                    $q->on('users.status', '!=', DB::raw("'deleted'"));
                })
                ->join('promotion_employee', function($q) use ($couponId) {
                    $q->on('users.user_id', '=', 'promotion_employee.user_id');
                    $q->on('promotion_employee.promotion_id', '=', DB::raw($couponId));
                });

        return $builder;
    }
}
