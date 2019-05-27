<?php
namespace Orbit\Events\Listeners\Gamification;

use User;

/**
 * class that does not reward user with game points
 *
 * @author zamroni <zamroni@dominopos.com>
 */
class NullPointRewarder implements PointRewarderInterface
{
    private $internalVarName;

    public function __construct($inVarName)
    {
        $this->internalVarName = $inVarName;
    }

    /**
     * get current gamification variable name
     * @return string current variable name
     */
    public function varName()
    {
        return $this->internalVarName;
    }

    /**
     * called when user is rewarded with point
     *
     * @var User $user, activated user
     * @var mixed $data, additional data (if any)
     */
    public function __invoke(User $user, $data = null)
    {
        //intentionally does nothing
    }
}
