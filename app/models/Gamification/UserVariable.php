<?php

namespace Orbit\Models\Gamification;

use Eloquent;

/**
 * Eloquent model for user variable
 *
 * @author zamroni <zamroni@dominopos.com>
 */
class UserVariable extends Eloquent
{
    protected $primaryKey = 'user_variable_id';
    protected $table = 'user_variables';
}
