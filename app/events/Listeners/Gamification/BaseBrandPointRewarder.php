<?php
namespace Orbit\Events\Listeners\Gamification;

use User;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Config;

/**
 * helper class that reward user with game points only when user
 * follow brand for the first time, or unfollow a brand
 *
 * @author zamroni <zamroni@dominopos.com>
 */
abstract class BaseBrandPointRewarder extends DecoratorRewarder
{
    protected $mongoClient;

    /**
     * cosntructor
     */
    public function __construct($rewarder)
    {
        parent::__construct($rewarder);
        $mongoConfig = Config::get('database.mongodb');
        $this->mongoClient = MongoClient::create($mongoConfig);
    }

    /**
     * get number of store  of brand followed  by user
     *
     * @param MongoClient mongoClient, instance of mongo client
     * @param string userId, user id of user
     * @param string brandId, user id of user
     */
    protected function getNumberOfStoreOfBrandFollowed($mongoClient, $userId, $baseMerchantId)
    {
        $queryString = [
            'user_id'     => $userId,
            'base_merchant_id'   => $objectId,
            'object_type' => 'store'
        ];

        $followData = $mongoClient->setQueryString($queryString)
                             ->setEndPoint('user-follows')
                             ->request('GET');
        return count($followData->data->records);
    }

    /**
     * called when user is follow/unfollow brand
     *
     * @var User $user, activated user
     * @var mixed $data, additional data (if any)
     */
    protected function rewardIfNotStoreOrGetNumberOfStore(User $user, $data)
    {
        //assignment is required for PHP < 7 to call __invoke() of a class
        $giveReward = $this->pointRewarder;
        if ($data->object_type !== 'store') {
            $giveReward($user, $data);
            return;
        }

        //handle store only
        return $this->getNumberOfStoreOfBrandFollowed(
            $this->mongoClient,
            $user->user_id,
            $data->object_id
        );
    }
}
