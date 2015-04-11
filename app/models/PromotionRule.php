<?php

class PromotionRule extends Eloquent
{
    /**
     * PromotionRule Model
     *
     * @author Tian <tian@dominopos.com>
     */

    protected $table = 'promotion_rules';

    protected $primaryKey = 'promotion_rule_id';

    protected $hidden = array('rule_object_type', 'rule_object_id1', 'rule_object_id2', 'rule_object_id3', 'rule_object_id4', 'rule_object_id5', 'is_cumulative_with_coupons', 'is_cumulative_with_promotions', 'coupon_redeem_rule_value');

    public function promotion()
    {
        return $this->belongsTo('Promotion', 'promotion_id', 'promotion_id');
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
