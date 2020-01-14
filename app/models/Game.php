<?php

class Game extends Eloquent
{
	use ModelStatusTrait;

    protected $primaryKey = 'game_id';

    protected $table = 'games';

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'game_id')
                    ->where('object_name', 'game');
    }

}