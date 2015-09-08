<?php

/**
 * A class to represent the translation of a category's strings to an alternate language.
 *
 * @method static \Illuminate\Database\Eloquent\Builder excludeDeleted()
 */
class CouponTranslation extends Eloquent
{
    use ModelStatusTrait;

    protected $table = 'coupon_translations';

    protected $primaryKey = 'coupon_translation_id';

    public function coupon()
    {
        return $this->belongsTo('Coupon', 'promotion_id', 'promotion_id');
    }

    public function language()
    {
        return $this->belongsTo('MerchantLanguage', 'merchant_language_id', 'merchant_language_id');
    }
}