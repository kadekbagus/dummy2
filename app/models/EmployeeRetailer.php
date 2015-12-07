<?php

class PromotionEmployee extends Eloquent
{
    /**
     * PromotionEmployee Model
     *
     * @author Tian <firmansyah@dominopos.com>
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
}
