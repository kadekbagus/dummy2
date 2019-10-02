<?php

class RgpUser extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'rgp_user_id';

    protected $table = 'rgp_users';

    public function getFullName()
    {
        return $this->username;
    }
}