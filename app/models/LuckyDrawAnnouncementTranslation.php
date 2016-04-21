<?php

/**
 * A class to represent the translation of a lucky draw's strings to an alternate language.
 *
 * @method static \Illuminate\Database\Eloquent\Builder excludeDeleted()
 */
class LuckyDrawAnnouncementTranslation extends Eloquent
{
    use ModelStatusTrait;

    protected $table = 'lucky_draw_announcement_translations';

    protected $primaryKey = 'lucky_draw_announcement_translation_id';

    public function luckydrawannouncement()
    {
        return $this->belongsTo('LuckyDrawAnnouncement', 'lucky_draw_announcement_id', 'lucky_draw_announcement_id');
    }

    public function language()
    {
        return $this->belongsTo('MerchantLanguage', 'merchant_language_id', 'language_id');
    }

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'lucky_draw_announcement_translation_id')
                    ->where('object_name', 'lucky_draw_announcement_translation');
    }

    public function media_orig()
    {
        return $this->media()->where('media_name_long', '=', 'lucky_draw_announcement_translation_image_orig');
    }
}
