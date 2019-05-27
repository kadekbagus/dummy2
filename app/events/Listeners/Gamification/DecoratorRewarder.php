<?php
namespace Orbit\Events\Listeners\Gamification;

use User;
use Log;

/**
 * decorator class that reward user one time only for particular object
 *
 * @author zamroni <zamroni@dominopos.com>
 */
abstract class DecoratorRewarder implements PointRewarderInterface
{
    /**
     * game point rewarder
     * @var PointRewarderInterface
     */
    protected $pointRewarder;

    public function __construct(PointRewarderInterface $pointRewarder)
    {
        $this->pointRewarder = $pointRewarder;
    }

    /**
     * get current gamification variable name
     * @return string current variable name
     */
    public function varName()
    {
        Log::info('DecoratorRewarder variableName', [$this->pointRewarder]);
        return $this->pointRewarder->varName();
    }

    /**
     * called when user is rewarded with point
     * here we will reward user with point if gamification variable
     * for particular object is not yet given
     *
     * @var User $user, activated user
     * @var mixed $data, additional data (if any)
     */
    abstract public function __invoke(User $user, $data = null);
}
