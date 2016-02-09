<?php

/**
 * A class to represent the translation of an promotion's strings to an alternate language.
 *
 * @property int $promotion_translation_id
 * @property int $promotion_id
 * @property int  $merchant_language_id
 *
 * @property string $promotion_name
 * @property string $description
 *
 * @property int $created_by
 * @property int $modified_by
 *
 * @method static \Illuminate\Database\Eloquent\Builder excludeDeleted()
 */
class PromotionTranslation extends Eloquent
{
    use ModelStatusTrait;

    protected $table = 'promotion_translations';

    protected $primaryKey = 'promotion_translation_id';

    public function promotion()
    {
        return $this->belongsTo('Promotion', 'promotion_id', 'promotion_id');
    }

    public function language()
    {
        return $this->belongsTo('MerchantLanguage', 'merchant_language_id', 'merchant_language_id');
    }

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'promotion_translation_id')
                    ->where('object_name', 'promotion_translation');
    }

    public function media_orig()
    {
        return $this->media()->where('media_name_long', '=', 'promotion_translation_image_orig');
    }
}