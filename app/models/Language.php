<?php

/**
 * Represents a global language that can be preferred by a user and can be used by a merchant.
 *
 * @property int $language_id
 * @property string $name
 */
class Language extends Eloquent
{
        /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $primaryKey = 'language_id';

    protected $table = 'languages';

    /**
     * Prefered language has many merchant language.
     */
    public function merchantLanguage()
    {
        return $this->hasMany('MerchantLanguage', 'language_id', 'language_id')->excludeDeleted();
    }


}
