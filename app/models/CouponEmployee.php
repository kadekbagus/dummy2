<?php

class CouponEmployee extends Eloquent
{
    /**
     * CouponEmployee Model
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */

    protected $primaryKey = 'promotion_employee_id';

    protected $table = 'promotion_employee';

    public function promotion()
    {
        return $this->belongsTo('Promotion', 'promotion_id', 'promotion_id');
    }

    public function employee()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function employeeMall()
    {
        return $this->belongsTo('Employee', 'user_id', 'user_id');
    }
}
