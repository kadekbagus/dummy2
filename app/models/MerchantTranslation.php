<?php

/**
 * A class to represent the translation of the merchant's strings to an alternate language.
 *
 * @property int $merchant_translation_id
 * @property int $merchant_id
 * @property int  $merchant_language_id
 *
 * @property string $name
 * @property string $description
 * @property string $ticket_header
 * @property string $ticket_footer
 *
 * @property int $created_by
 * @property int $modified_by
 *
 * @method static \Illuminate\Database\Eloquent\Builder excludeDeleted()
 */
class MerchantTranslation extends Eloquent
{
    use ModelStatusTrait;

    protected $table = 'merchant_translations';

    protected $primaryKey = 'merchant_translation_id';

    public function merchant()
    {
        return $this->belongsTo('Merchant', 'merchant_id', 'merchant_id');
    }

    public function language()
    {
        return $this->belongsTo('MerchantLanguage', 'merchant_language_id', 'language_id');
    }

}
