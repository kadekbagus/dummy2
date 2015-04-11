<?php

class CouponRule extends Eloquent
{
    /**
     * CouponRule Model
     *
     * @author Tian <tian@dominopos.com>
     */

    protected $table = 'promotion_rules';

    protected $primaryKey = 'promotion_rule_id';

    public function coupon()
    {
        return $this->belongsTo('Coupon', 'promotion_id', 'promotion_id');
    }

    public function ruleproduct()
    {
        return $this->belongsTo('Product', 'rule_object_id1', 'product_id');
    }

    public function rulecategory1()
    {
        return $this->belongsTo('Category', 'rule_object_id1', 'category_id');
    }

    public function rulecategory2()
    {
        return $this->belongsTo('Category', 'rule_object_id2', 'category_id');
    }

    public function rulecategory3()
    {
        return $this->belongsTo('Category', 'rule_object_id3', 'category_id');
    }

    public function rulecategory4()
    {
        return $this->belongsTo('Category', 'rule_object_id4', 'category_id');
    }

    public function rulecategory5()
    {
        return $this->belongsTo('Category', 'rule_object_id5', 'category_id');
    }

    public function discountproduct()
    {
        return $this->belongsTo('Product', 'discount_object_id1', 'product_id');
    }

    public function discountcategory1()
    {
        return $this->belongsTo('Category', 'discount_object_id1', 'category_id');
    }

    public function discountcategory2()
    {
        return $this->belongsTo('Category', 'discount_object_id2', 'category_id');
    }

    public function discountcategory3()
    {
        return $this->belongsTo('Category', 'discount_object_id3', 'category_id');
    }

    public function discountcategory4()
    {
        return $this->belongsTo('Category', 'discount_object_id4', 'category_id');
    }

    public function discountcategory5()
    {
        return $this->belongsTo('Category', 'discount_object_id5', 'category_id');
    }
}
