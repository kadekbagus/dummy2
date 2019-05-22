<?php
namespace Orbit\Events\Listeners\Gamification;

use DB;
use User;
use Orbit\Models\Gamification\UserVariable;
use Orbit\Models\Gamification\Variable;
use Orbit\Models\Gamification\UserGameEvent;
use DateTime;

/**
 * Helper class that reward user with game points
 *
 * @author zamroni <zamroni@dominopos.com>
 */
class PointRewarder
{
    /**
     * gamification variable name
     * @var string
     */
    private $varName;

    public function __construct($varName)
    {
        $this->varName = $varName;
    }

    private function updateUserVariable($user, $gamificationVar)
    {
        $userVar = UserVariable::where('variable_id', $gamificationVar->variable_id)
            ->where('user_id', $user->user_id)
            ->limit(1)
            ->first();

        if (! $userVar) {
            $userVar = new UserVariable();
            $userVar->variable_id = $gamificationVar->variable_id;
            $userVar->user_id = $user->user_id;
        }
        $userVar->value = $userVar->value + 1;
        $userVar->total_points = $userVar->total_points + $gamificationVar->point;
        $userVar->save();

        $user->total_game_points = $user->total_game_points + $gamificationVar->point;
        $user->save();
        return $userVar;
    }

    private function updateUserGameEvent($user, $gamificationVar)
    {
        $userGameEv = new UserGameEvent();
        $userGameEv->variable_id = $gamificationVar->variable_id;
        $userGameEv->user_id = $user->user_id;
        $userGameEv->point = $gamificationVar->point;
        $userGameEv->created_at = new DateTime();
        $userGameEv->updated_at = new DateTime();
        $userGameEv->save();
        return $userGameEv;
    }

    /**
     * called when user is rewarded with point
     *
     * @var User $user, activated user
     */
    public function __invoke(User $user)
    {
        $gamificationVar = Variable::where('variable_slug', $this->varName)
            ->limit(1)
            ->first();
        DB::transaction(function() use ($user, $gamificationVar) {
            $this->updateUserVariable($user, $gamificationVar);
            $this->updateUserGameEvent($user, $gamificationVar);
        });

    }
}
