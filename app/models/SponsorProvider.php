<?php

class SponsorProvider extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'sponsor_provider_id';

    protected $table = 'sponsor_providers';


    public function creditCards()
    {
        return $this->hasMany('SponsorCreditCard', 'sponsor_provider_id', 'sponsor_provider_id');
    }
}