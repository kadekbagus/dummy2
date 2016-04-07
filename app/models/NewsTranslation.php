<?php

/**
 * A class to represent the translation of an news's strings to an alternate language.
 *
 * @property int $news_translation_id
 * @property int $news_id
 * @property int  $merchant_language_id
 *
 * @property string $news_name
 * @property string $description
 *
 * @property int $created_by
 * @property int $modified_by
 *
 * @method static \Illuminate\Database\Eloquent\Builder excludeDeleted()
 */
class NewsTranslation extends Eloquent
{
    use ModelStatusTrait;

    protected $table = 'news_translations';

    protected $primaryKey = 'news_translation_id';

    public function news()
    {
        return $this->belongsTo('NewsModel', 'news_id', 'news_id');
    }

    public function language()
    {
        return $this->belongsTo('Language', 'merchant_language_id', 'language_id');
    }

    public function media()
    {
        return $this->hasMany('Media', 'object_id', 'news_translation_id')
                    ->where('object_name', 'news_translation');
    }

    public function media_orig()
    {
        return $this->media()->where('media_name_long', '=', 'news_translation_image_orig');
    }

}