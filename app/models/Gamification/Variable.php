<?php

namespace Orbit\Models\Gamification;

use Eloquent;

/**
 * Eloquent model for gamification variable
 *
 * @author zamroni <zamroni@dominopos.com>
 */
class Variable extends Eloquent
{
    protected $primaryKey = 'variable_id';
    protected $table = 'variables';
}
