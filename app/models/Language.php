<?php

/**
 * Represents a global language that can be preferred by a user and can be used by a merchant.
 *
 * @property int $language_id
 * @property string $name
 */
class Language extends Eloquent
{
    protected $primaryKey = 'language_id';

    protected $table = 'languages';

}
