<?php
namespace Orbit\Events\Listeners\Gamification;

use User;
use Orbit\Models\Gamification\Variable;
use Orbit\Models\Gamification\UserGameEvent;

/**
 * decorator class that reward user one time only for particular object
 *
 * @author zamroni <zamroni@dominopos.com>
 */
class OneTimeReward extends DecoratorRewarder
{
    /**
     * called when user is rewarded with point
     * here we will reward user with point if gamification variable
     * for particular object is not yet given
     *
     * @var User $user, activated user
     * @var mixed $data, additional data (if any)
     */
    public function __invoke(User $user, $data = null)
    {
        if (is_array($data)) {
            $data = (object) $data;
        }

        //assignment is required for PHP < 7 to call __invoke() of a class
        $giveReward = $this->pointRewarder;

        if (empty($data) || !(isset($data->object_id) && isset($data->object_type))) {
            //no related data given, just give reward
            $giveReward($user, $data);
        }

        $gamificationVar = Variable::where('variable_slug', $this->variableName())->first();
        $rewardHistory = UserGameEvent::where('variable_id', $gamificationVar->variable_id)
            ->where('user_id', $user->user_id)
            ->where('object_id', $data->object_id)
            ->where('object_type', $data->object_type)
            ->first();

        if (! $rewardHistory) {
            //no history for particular variable for object given to this user
            //so reward them
            $giveReward($user, $data);
        }
    }
}
