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

    /**
     * Game - Digital Product relation
     * @return [type] [description]
     */
    public function digital_products()
    {
        return $this->belongsToMany(DigitalProduct::class, 'digital_product_game');
    }

}
