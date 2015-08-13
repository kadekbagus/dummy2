<?php

/**
 * A class to represent the translation of a category's strings to an alternate language.
 *
 * @property int $category_translation_id
 * @property int $category_id
 * @property int  $merchant_language_id
 *
 * @property string $category_name
 * @property string $description
 *
 * @method static \Illuminate\Database\Eloquent\Builder excludeDeleted()
 */
class CategoryTranslation extends Eloquent
{
    use ModelStatusTrait;

    protected $table = 'category_translations';

    protected $primaryKey = 'category_translation_id';

    public function category()
    {
        return $this->belongsTo('Category', 'category_id', 'category_id');
    }

    public function language()
    {
        return $this->belongsTo('MerchantLanguage', 'merchant_language_id', 'merchant_language_id');
    }
}