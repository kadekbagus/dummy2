<?php
namespace Orbit\Events\Listeners\Gamification;

use User;
use DateTime;

/**
 * helper class that throttle speed when we reward user with game points
 * for same variable for same object
 *
 * @author zamroni <zamroni@dominopos.com>
 */
class ThrottledRewarder extends DecoratorRewarder
{
    /**
     * delay time in seconds
     * @var int
     */
    private $delayTime;

    /**
     * constructor
     * @param PointRewarderInterface $pointRewarder [description]
     * @param int       $delayTime     time to delay to give same reward
     *                  for same user for same object (default 5 minute)
     */
    public function __construct(PointRewarderInterface $pointRewarder, $delayTime = 300)
    {
        parent::__construct($pointRewarder);
        $this->delayTime = $delayTime;
    }

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

        $gamificationVar = Variable::where('variable_slug', $this->variableName())->first();
        $rewardHistory = UserGameEvent::where('variable_id', $gamificationVar->variable_id)
            ->where('user_id', $user->user_id)
            ->where('object_id', $data->object_id)
            ->where('object_type', $data->object_type)
            ->first();

        if (! $rewardHistory) {
            //no history for reward for same user for same object, so just give it
            $giveReward($user, $data);
        } else {
            $now = new DateTime();
            $delay = DateInterval::createFromDateString($this->delayTime . ' seconds');
            $newTime = $rewardHistory->updated_at->add($delay);
            $interval = $now->diff($newTime);
            if ($interval->invert) {
                //exceed delay time, so reward user
                $giveReward($user, $data);
            }
        }
    }
}
