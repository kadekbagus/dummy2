<?php

/**
 * A class to represent the translation of a lucky draw's strings to an alternate language.
 *
 * @method static \Illuminate\Database\Eloquent\Builder excludeDeleted()
 */
class LuckyDrawTranslation extends Eloquent
{
    use ModelStatusTrait;

    protected $table = 'lucky_draw_translations';

    protected $primaryKey = 'lucky_draw_translation_id';

    public function luckydraw()
    {
        return $this->belongsTo('LuckyDraw', 'lucky_draw_id', 'lucky_draw_id');
    }

    public function language()
    {
        return $this->belongsTo('MerchantLanguage', 'merchant_language_id', 'language_id');
    }

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'lucky_draw_translation_id')
                    ->where('object_name', 'lucky_draw_translation');
    }

    public function media_orig()
    {
        return $this->media()->where('media_name_long', '=', 'lucky_draw_translation_image_orig');
    }
}
