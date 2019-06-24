<?php

namespace Orbit\Models\Gamification;

use Eloquent;

/**
 * Eloquent model for extended_user table
 *
 * @author zamroni <zamroni@dominopos.com>
 */
class UserExtended extends Eloquent
{
    protected $primaryKey = 'extended_user_id';
    protected $table = 'extended_users';
}
