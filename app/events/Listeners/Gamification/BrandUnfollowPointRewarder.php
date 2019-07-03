<?php
namespace Orbit\Events\Listeners\Gamification;

use User;

/**
 * helper class that reward user with game points only when user unfollow a brand
 *
 * @author zamroni <zamroni@dominopos.com>
 */
class BrandUnfollowPointRewarder extends BaseBrandPointRewarder
{
    /**
     * called when user is unfollow brand
     *
     * @var User $user, activated user
     * @var mixed $data, additional data (if any)
     */
    public function __invoke(User $user, $data = null)
    {
        $numberofStoreOfBrand = $this->rewardIfNotStoreOrGetNumberOfStore($user, $data);

        //unfollow, and number of store === 0 means, user stop following brand
        if ($numberOfStoreOfBrand === 0) {
            //punish user
            $giveReward($user, $data);
        }
    }
}
