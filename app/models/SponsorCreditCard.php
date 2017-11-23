<?php

class SponsorCreditCard extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'sponsor_credit_card_id';

    protected $table = 'sponsor_credit_cards';
}