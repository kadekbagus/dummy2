<?php

class PaymentProvider extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'payment_provider_id';

    protected $table = 'payment_providers';

}