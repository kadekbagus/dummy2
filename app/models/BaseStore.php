<?php

class BaseStore extends Eloquent
{
    /**
     * BaseStore Model
     *
     * @author Irianto <irianto@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $primaryKey = 'base_store_id';

    protected $table = 'base_stores';
}
