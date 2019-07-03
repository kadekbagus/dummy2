<?php
namespace Orbit\Events\Listeners\Gamification;

use User;

/**
 * helper class that reward user with game points only when user
 * follow brand for the first time
 *
 * @author zamroni <zamroni@dominopos.com>
 */
class BrandFollowPointRewarder extends BaseBrandPointRewarder
{
    /**
     * called when user is follow brand
     *
     * @var User $user, activated user
     * @var mixed $data, additional data (if any)
     */
    public function __invoke(User $user, $data = null)
    {
        $numberOfStoreOfBrand = $this->rewardIfNotStoreOrGetNumberOfStore($user, $data);

        //follow, and number of store === 1 means, user following brand
        //for this first time
        if ($numberOfStoreOfBrand === 1) {
            //user eligible, give reward
            //assignment is required for PHP < 7 to call __invoke() of a class
            $giveReward = $this->pointRewarder;
            $giveReward($user, $data);
        }
    }
}
