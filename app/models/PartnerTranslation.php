<?php

class PartnerTranslation extends Eloquent
{
    use ModelStatusTrait;

    protected $table = 'partner_translations';

    protected $primaryKey = 'partner_translation_id';

    public function partner()
    {
        return $this->belongsTo('Partner', 'partner_id', 'partner_id');
    }

    public function language()
    {
        return $this->belongsTo('Language', 'language_id', 'language_id');
    }
}