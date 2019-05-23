<?php namespace Orbit\Controller\API\v1\Pub\UserProfile;

use Orbit\Helper\MongoDB\Client as MongoClient;
use Config;
use User;
use DB;

/**
 * Profile helper functions.
 */
class ProfileHelper
{
    private $mongoClient = null;

    /**
     * Get total of user related content.
     *
     * @param  [type] $userId [description]
     * @return [type]         [description]
     */
    public function getTotalContent($userId = null)
    {
        $profileTotal = (object) [
            'reviews' => 0,
            'photos' => 0,
            'following' => 0,
        ];

        $mongoConfig = Config::get('database.mongodb');
        $this->mongoClient = MongoClient::create($mongoConfig);

        $profileTotal->reviews = $this->getTotalReview($userId);
        $profileTotal->photos = $this->getTotalPhotos($userId);
        $profileTotal->following = $this->getTotalFollowing($userId);

        return $profileTotal;
    }

    /**
     * Get total review per User.
     *
     * @param  [type] $userId [description]
     * @return [type]         [description]
     */
    private function getTotalReview($userId = null)
    {
        $endPoint = "reviews/{$userId}/count";
        $response = $this->mongoClient->setEndPoint($endPoint)->request('GET');
        $totalRecords = 0;

        if (isset($response->data) && ! empty($response->data)) {
            $totalRecords = $response->data->total_records;
        }

        return $totalRecords;
    }

    /**
     * Get total photos per User.
     *
     * @param  [type] $userId [description]
     * @return [type]         [description]
     */
    private function getTotalPhotos($userId = null)
    {
        $endPoint = "reviews/{$userId}/list";
        $response = $this->mongoClient->setEndPoint($endPoint)->request('GET');
        $totalPhotos = 0;

        if (isset($response->data) && ! empty($response->data)) {
            foreach($response->data->records as $review) {
                if (isset($review->images)) {
                    foreach($review->images as $image) {
                        if ($image[0]->approval_status === 'approved') {
                            $totalPhotos++;
                            break;
                        }
                    }
                }
            }
        }

        return $totalPhotos;
    }

    /**
     * Get total of following per User.
     *
     * @param  [type] $userId [description]
     * @return [type]         [description]
     */
    private function getTotalFollowing($userId = null)
    {
        $endPoint = "user-follows";
        $queryString = ['user_id' => $userId];
        $response = $this->mongoClient->setQueryString($queryString)->setEndPoint($endPoint)->request('GET');
        $totalFollowing = 0;

        if (isset($response->data) && ! empty($response->data)) {
            $brandIds = [];
            $mallIds = [];
            foreach($response->data->records as $following) {
                if (empty($following->base_merchant_id)) {
                    $totalFollowing++;
                }
                else if (! in_array($following->base_merchant_id, $brandIds)) {
                    $brandIds[] = $following->base_merchant_id;
                    $totalFollowing++;
                }
            }
        }

        return $totalFollowing;
    }

    /**
     * Get rank for given user.
     *
     * At the moment we will throttle data fetching per $take number,
     *
     * Downside is, if user is in the pretty far last order,
     * it will costs more DB request to reach the rank.
     *
     * @todo  find a better way to calculate the rank.
     * @param  [type] $userId [description]
     * @return [type]         [description]
     */
    public function getUserRank($userId = null)
    {
        $userRank = (object) [
            'real_rank' => 0,
            'grouped_rank' => 0,
            'loop' => 0,
        ];

        $lastPoint = 0;
        $lastRank = 0;
        $skip = 0;
        $take = 5000;
        $found = false;

        while(! $found) {
            $userRank->loop++;
            $usersRanking = DB::table('users')->select('user_id', 'total_game_points')->orderBy('total_game_points', 'desc')->skip($skip)->take($take)->get();

            foreach($usersRanking as $rank) {
                $userRank->real_rank++;

                if ($rank->total_game_points !== $lastPoint) {
                    $lastPoint = $rank->total_game_points;
                    $userRank->grouped_rank++;
                }

                if ($rank->user_id === $userId) {
                    $found = true;
                    break;
                }
            }

            $skip = $take;
        }

        return $userRank;
    }
}
