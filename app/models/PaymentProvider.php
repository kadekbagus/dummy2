<?php

class PaymentProvider extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'payment_provider_id';

    protected $table = 'payment_providers';


    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'payment_provider_id')
                    ->where('object_name', 'wallet_operator');
    }

    public function mediaLogo()
    {
        return $this->media()->where('media_name_id', 'wallet_operator_logo');
    }
}