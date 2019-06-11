<?php
namespace Orbit\Events\Listeners\Gamification;

use User;

/**
 * helper class that reward user with game points only if user is activated
 *
 * @author zamroni <zamroni@dominopos.com>
 */
class ActivatedUserRewarder extends DecoratorRewarder
{
    /**
     * called when user is rewarded with point
     *
     * @var User $user, activated user
     * @var mixed $data, additional data (if any)
     */
    public function __invoke(User $user, $data = null)
    {
        //assignment is required for PHP < 7 to call __invoke() of a class
        $giveReward = $this->pointRewarder;

        if ($user->status === 'active') {
            //user is activated, give reward
            $giveReward($user, $data);
        }
    }
}
