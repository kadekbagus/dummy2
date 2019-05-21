<?php
namespace Orbit\Events\Listeners\Gamification;

use DB;
use User;
use Orbit\Gamification\UserVariable;
use Orbit\Gamification\Variable;
use Orbit\Gamification\UserGameEvent;

/**
 * Event listener for orbit.user.activation.success
 *
 * @author zamroni <zamroni@dominopos.com>
 */
class UserActivation
{
    private function updateUserVariable($user, $gamificationVar)
    {
        $userVar = UserVariable::where('variable_id', $gamificationVar->variable_id)
            ->where('user_id', $user->user_id);

        if (! $userVar) {
            $userVar = new UserVariable();
            $userVar->variable_id = $gamificationVar->variable_id;
            $userVar->user_id = $user->user_id;
        }
        $userVar->value = $userVar->value + 1;
        $userVar->total_points = $userVar->total_points + $gamificationVar->point;
        $userVar->save();
        return $userVar;
    }

    private function updateUserGameEvent($user, $gamificationVar)
    {
        $userGameEv = new UserGameEvent();
        $userGameEv->variable_id = $gamificationVar->variable_id;
        $userGameEv->user_id = $user->user_id;
        $userGameEv->point = $gamificationVar->point;
        $userGameEv->save();
        return $userVar;
    }

    /**
     * called by event dispatcher when orbit.user.activation.success is fired
     *
     * @var User $user, activated user
     */
    public function __invoke($user)
    {
        $gamificationVar = Variable::where('variable_slug')->limit(1);
        DB::transaction(function() use ($user, $gamificationVar) {
            $this->updateUserVariable($user, $gamificationVar);
            $this->updateUserGameEvent($user, $gamificationVar);
        });

    }
}
