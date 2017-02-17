<?php

class ObjectSupportedLanguage extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'object_supported_language_id';

    protected $table = 'object_supported_language';

    public function language()
    {
        return $this->hasOne('Language', 'language_id', 'language_id');
    }

}
