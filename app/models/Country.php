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

    public function baseMerchant()
    {
        return $this->belongsTo('BaseMerchant', 'country_id', 'country_id');
    }

    public function cities()
    {
        return $this->hasMany('MallCity');
    }
}
