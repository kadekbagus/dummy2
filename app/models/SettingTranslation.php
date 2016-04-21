<?php

/**
 * A class to represent the translation of an event's strings to an alternate language.
 *
 * @property int $setting_start_button_id
 * @property int $setting_id
 * @property int  $merchant_language_id
 *
 * @property string $event_name
 *
 * @property int $created_by
 * @property int $modified_by
 *
 * @method static \Illuminate\Database\Eloquent\Builder excludeDeleted()
 */
class SettingTranslation extends Eloquent
{
    use ModelStatusTrait;

    protected $table = 'setting_translations';

    protected $primaryKey = 'setting_translation_id';

    public function setting()
    {
        return $this->belongsTo('SettingModel', 'setting_id', 'setting_id');
    }

    public function language()
    {
        return $this->belongsTo('MerchantLanguage', 'merchant_language_id', 'language_id');
    }
}