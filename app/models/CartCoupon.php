<?php

class CartCoupon extends Eloquent
{
    /**
     * Cart Coupon Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    use ModelStatusTrait;

    protected $table = 'cart_coupons';

    protected $primaryKey = 'cart_coupon_id';

    public function issuedcoupon(){
        return $this->belongsTo('IssuedCoupon', 'issued_coupon_id', 'issued_coupon_id');
    }

    public function cart(){
        return $this->belongsTo('Cart', 'object_id', 'cart_id');
    }

    public function cartdetail(){
        return $this->belongsTo('CartDetail', 'object_id', 'cart_detail_id');
    }
}