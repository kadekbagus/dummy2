<?php

class Guest extends Eloquent implements UserInterface
{
	protected $primaryKey = 'user_id';

	protected $table = 'users';

	public function user()
	{
		return $this->belongsToMany('User', 'user_guest', 'guest_id', 'user_id');
	}
}
