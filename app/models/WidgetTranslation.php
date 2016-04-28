<?php

/**
 * A class to represent the translation of an event's strings to an alternate language.
 *
 * @property int $event_translation_id
 * @property int $event_id
 * @property int $merchant_language_id
 *
 * @property string $widget_slogan
 *
 * @property int $created_by
 * @property int $modified_by
 *
 * @method static \Illuminate\Database\Eloquent\Builder excludeDeleted()
 */
class WidgetTranslation extends Eloquent
{
    use ModelStatusTrait;

    protected $table = 'widget_translations';

    protected $primaryKey = 'widget_translation_id';

    public function event()
    {
        return $this->belongsTo('Widget', 'widget_id', 'widget_id');
    }

    public function language()
    {
        return $this->belongsTo('MerchantLanguage', 'merchant_language_id', 'language_id');
    }
}