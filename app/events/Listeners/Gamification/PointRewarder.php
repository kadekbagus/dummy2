<?php
namespace Orbit\Events\Listeners\Gamification;

use DB;
use User;
use Orbit\Models\Gamification\UserVariable;
use Orbit\Models\Gamification\Variable;
use Orbit\Models\Gamification\UserGameEvent;
use DateTime;
use Log;

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

    private function prepareAdditionalData($userGameEv, $data)
    {
        if (isset($data->object_id) && ! empty($data->object_id)) {
            $userGameEv->object_id = $data->object_id;
        }

        if (isset($data->object_type) && ! empty($data->object_type)) {
            $userGameEv->object_type = $data->object_type;
        }

        if (isset($data->object_name) && ! empty($data->object_name)) {
            $userGameEv->object_name = $data->object_name;
        }

        if (isset($data->country_id)) {
            $userGameEv->country_id = $data->country_id;
        }

        if (isset($data->city) && ! empty($data->city)) {
            $userGameEv->city = $data->city;
        }

        return $userGameEv;
    }

    private function updateUserGameEvent($user, $gamificationVar, $data)
    {
        $userGameEv = new UserGameEvent();
        $userGameEv->variable_id = $gamificationVar->variable_id;
        $userGameEv->user_id = $user->user_id;
        $userGameEv->point = $gamificationVar->point;

        if (! empty($data)) {
            if (is_array($data)) {
                $data = (object) $data;
            }
            $userGameEv = $this->prepareAdditionalData($userGameEv, $data);
        }

        $userGameEv->save();
        return $userGameEv;
    }

    /**
     * called when user is rewarded with point
     *
     * @var User $user, activated user
     * @var object $data, additional data (if any)
     */
    public function __invoke(User $user, $data = null)
    {
        $gamificationVar = Variable::where('variable_slug', $this->varName)
            ->limit(1)
            ->first();
        DB::transaction(function() use ($user, $gamificationVar, $data) {
            $this->updateUserVariable($user, $gamificationVar);
            $this->updateUserGameEvent($user, $gamificationVar, $data);
        });
    }
}
