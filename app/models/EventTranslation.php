<?php

/**
 * A class to represent the translation of an event's strings to an alternate language.
 *
 * @property int $event_translation_id
 * @property int $event_id
 * @property int  $merchant_language_id
 *
 * @property string $event_name
 * @property string $description
 *
 * @method static \Illuminate\Database\Eloquent\Builder excludeDeleted()
 */
class EventTranslation extends Eloquent
{
    use ModelStatusTrait;

    protected $table = 'event_translations';

    protected $primaryKey = 'event_translation_id';

    public function event()
    {
        return $this->belongsTo('EventModel', 'event_id', 'event_id');
    }

    public function language()
    {
        return $this->belongsTo('MerchantLanguage', 'merchant_language_id', 'merchant_language_id');
    }

}