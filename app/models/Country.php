<?php
/**
 * Model for representing the countries table.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class Country extends Eloquent
{
    protected $primaryKey = 'country_id';
    protected $table = 'countries';
}
