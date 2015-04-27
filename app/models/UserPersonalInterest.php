<?php

class UserPersonalInterest extends Eloquent
{
    /**
     * UserPersonalInterest Model
     *
     * @author Tian <tian@dominopos.com>
     */

    protected $table = 'user_personal_interest';

    protected $primaryKey = 'user_personal_interest_id';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function personal_interest()
    {
        return $this->belongsTo('PersonalInterest', 'personal_interest_id', 'personal_interest_id');
    }
}
