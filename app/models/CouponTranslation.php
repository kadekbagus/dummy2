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
        return $this->belongsTo('Language', 'merchant_language_id', 'language_id');
    }

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'coupon_translation_id')
                    ->where('object_name', 'coupon_translation');
    }

    public function media_orig()
    {
        return $this->media()->where('media_name_long', '=', 'coupon_translation_image_orig');
    }
}