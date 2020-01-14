<?php

/**
 * Game Model.
 */
class Game extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'games';

    protected $primaryKey = 'game_id';
}
