<?php

class Partner extends Eloquent
{
        /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'partners';
    protected $primaryKey = 'partner_id';
}