<?php
class Coupon extends Eloquent
{
    /**
     * Coupon Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    /**
     * Use Trait PromotionTypeTrait so we only displaying records with value
     * `is_coupon` = 'N'
     */
    use PromotionCouponTrait;

    /**
     * Column name which determine the type of Promotion or Coupon.
     */
    const OBJECT_TYPE = 'is_coupon';

    protected $table = 'promotions';

    protected $primaryKey = 'promotion_id';

    public function couponRule()
    {
        return $this->hasOne('CouponRule', 'promotion_id', 'promotion_id');
    }

    public function mall()
    {
        return $this->belongsTo('Retailer', 'merchant_id', 'merchant_id')->isMall();
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    public function tenants()
    {
        return $this->belongsToMany('Retailer', 'promotion_retailer', 'promotion_id', 'retailer_id');
    }

    public function issuedCoupons()
    {
        return $this->hasMany('IssuedCoupon', 'promotion_id', 'promotion_id');
    }

    /**
     * Coupon has many uploaded media.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'promotion_id')
                    ->where('object_name', 'coupon');
    }

    /**
     * Accessor for empty product image
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @param string $value - image path
     * @return string $value
     */
    public function getImageAttribute($value)
    {
        if (empty($value)) {
            return 'mobile-ci/images/default_product.png';
        }
        return ($value);
    }
}
