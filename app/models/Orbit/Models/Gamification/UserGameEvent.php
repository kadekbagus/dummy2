<?php

namespace Orbit\Models\Gamification;

use Eloquent;

class UserGameEvent extends Eloquent
{
    protected $primaryKey = 'user_game_event_id';
    protected $table = 'user_game_events';
}
