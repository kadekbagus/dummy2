<?php
namespace Orbit\Events\Listeners\Gamification;

use User;

/**
 * interface for any class having capability to reward user with game points
 *
 * @author zamroni <zamroni@dominopos.com>
 */
interface PointRewarderInterface
{
    /**
     * get current gamification variable name
     * @return string current variable name
     */
    public function variableName();

    /**
     * called when user is rewarded with point
     *
     * @var User $user, activated user
     * @var mixed $data, additional data (if any)
     */
    public function __invoke(User $user, $data = null);
}
