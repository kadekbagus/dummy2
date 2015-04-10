<?php

class CartDetail extends Eloquent
{
    /**
     * Cart Detail Model
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    use ModelStatusTrait;

    protected $table = 'cart_details';

    protected $primaryKey = 'cart_detail_id';

    public function cart()
    {
        return $this->belongsTo('Cart', 'cart_id', 'cart_id');
    }

    public function product()
    {
        return $this->hasOne('Product', 'product_id', 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo('ProductVariant', 'product_variant_id', 'product_variant_id');
    }

    public function attributeValue1()
    {
        return $this->variant()->join('product_attribute_values', 'product_attribute_values.product_attribute_value_id', '=', 'product_variants.product_attribute_value_id1');
    }

    public function attributeValue2()
    {
        return $this->variant()->join('product_attribute_values', 'product_attribute_values.product_attribute_value_id', '=', 'product_variants.product_attribute_value_id2');
    }

    public function attributeValue3()
    {
        return $this->variant()->join('product_attribute_values', 'product_attribute_values.product_attribute_value_id', '=', 'product_variants.product_attribute_value_id3');
    }

    public function attributeValue4()
    {
        return $this->variant()->join('product_attribute_values', 'product_attribute_values.product_attribute_value_id', '=', 'product_variants.product_attribute_value_id4');
    }

    public function attributeValue5()
    {
        return $this->variant()->join('product_attribute_values', 'product_attribute_values.product_attribute_value_id', '=', 'product_variants.product_attribute_value_id5');
    }

    public function tax1()
    {
        return $this->product->tax1();
    }

    public function tax2()
    {
        return $this->product->tax2();
    }
}