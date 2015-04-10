<?php
/**
 * Model for represeting the settings table.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class Setting extends Eloquent
{
    protected $table = 'settings';
    protected $primaryKey = 'setting_id';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;
}
