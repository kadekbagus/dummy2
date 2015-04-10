<?php

class Cart extends Eloquent
{
    /**
     * Cart Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    use ModelStatusTrait;

    const CART_INCREMENT = 111111;

    protected $table = 'carts';

    protected $primaryKey = 'cart_id';

    public function details()
    {
        return $this->hasMany('CartDetail', 'cart_id', 'cart_id');
    }

    public function users()
    {
        return $this->belongsTo('User', 'customer_id', 'user_id');
    }

}