<?php

class AffectedGroupName extends Eloquent
{
    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'affected_group_names';
    protected $primaryKey = 'affected_group_name_id';
}