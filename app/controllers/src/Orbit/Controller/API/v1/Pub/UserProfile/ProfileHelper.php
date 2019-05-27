<?php namespace Orbit\Controller\API\v1\Pub\UserProfile;

use Orbit\Helper\MongoDB\Client as MongoClient;
use Config;
use User;
use DB;
use Validator;
use Language;

/**
 * Profile helper functions.
 */
class ProfileHelper
{
    private $mongoClient = null;
    protected $valid_language = NULL;

    public static function create()
    {
        return new static();
    }


    function __construct()
    {
        $mongoConfig = Config::get('database.mongodb');
        $this->mongoClient = MongoClient::create($mongoConfig);
    }

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

        $profileTotal->reviews = $this->getTotalReview($userId);
        $profileTotal->photos = $this->getTotalPhotos($userId);
        $profileTotal->following = $this->getTotalFollowing($userId);

        return $profileTotal;
    }

    /**
     * Get user's reviews.
     *
     * @param  [type] $userId [description]
     * @return [type]         [description]
     */
    public function getReviews($userId = null)
    {
        $endPoint = "reviews";
        return $this->mongoClient->setQueryString(['user_id' => $userId])->setEndPoint($endPoint)->request('GET');
    }

    public function getPhotos($userId = null)
    {
        $reviews = $this->getReviews($userId);
        $photos = [
            'total_records' => 0,
            'records' => [],
        ];

        if (isset($reviews->data) && ! empty($reviews->data)) {
            foreach($reviews->data->records as $review) {
                if (isset($review->images)) {
                    foreach($review->images as $image) {
                        if ($image[0]->approval_status === 'approved') {
                            $photos['total_records']++;
                            $photos['records'][] = [
                                'review_id' => $review->_id,
                                'object_id' => $review->object_id,
                                'object_type' => $review->object_type,
                                'store_id' => $review->store_id,
                                'store_name' => $review->store_name,
                                'mall_id' => $review->location_id,
                                'mall_name' => $review->mall_name,
                                'desktop_thumb' => ! empty($image[1]->cdn_url) ? $image[1]->cdn_url : $image[1]->url,
                                'mobile_thumb' => ! empty($image[2]->cdn_url) ? $image[2]->cdn_url : $image[2]->url,
                                'desktop_medium' => ! empty($image[3]->cdn_url) ? $image[3]->cdn_url : $image[3]->url,
                                'mobile_medium' => ! empty($image[4]->cdn_url) ? $image[4]->cdn_url : $image[4]->url,
                            ];
                        }
                    }
                }
            }
        }

        return $photos;
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
     * @todo  should calculate the count in mongo.
     * @param  [type] $userId [description]
     * @return [type]         [description]
     */
    private function getTotalPhotos($userId = null)
    {
        $reviews = $this->getReviews($userId);
        $totalPhotos = 0;

        if (isset($reviews->data) && ! empty($reviews->data)) {
            foreach($reviews->data->records as $review) {
                if (isset($review->images)) {
                    foreach($review->images as $image) {
                        if ($image[0]->approval_status === 'approved') {
                            $totalPhotos++;
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

    public function registerCustomValidation() {
        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->valid_language = $language;
            return TRUE;
        });
    }

    public function getValidLanguage()
    {
        return $this->valid_language;
    }
}
