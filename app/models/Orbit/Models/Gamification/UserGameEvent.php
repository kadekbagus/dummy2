<?php

namespace Orbit\Models\Gamification;

use Eloquent;

/**
 * Eloquent model for user variable
 *
 * @author zamroni <zamroni@dominopos.com>
 */
class UserGameEvent extends Eloquent
{
    protected $primaryKey = 'user_game_event_id';
    protected $table = 'user_game_events';
}
