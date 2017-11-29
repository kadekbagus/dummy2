<?php

class SponsorCreditCard extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'sponsor_credit_card_id';

    protected $table = 'sponsor_credit_cards';

    public function translation()
    {
        return $this->hasMany('SponsorCreditCardTranslation', 'sponsor_credit_card_id', 'sponsor_credit_card_id');
    }

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'sponsor_credit_card_id')->where('object_name', 'sponsor_credit_card');
    }
}