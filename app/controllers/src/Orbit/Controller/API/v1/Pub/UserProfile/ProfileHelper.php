<?php namespace Orbit\Controller\API\v1\Pub\UserProfile;

use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\Util\CdnUrlGenerator;
use Config;
use User;
use PaymentTransaction;
use DB;
use Cache;
use Validator;
use Language;

/**
 * Profile helper functions.
 */
class ProfileHelper
{
    private $mongoClient = null;

    private $reviews = null;

    protected $valid_language = NULL;

    private $userId = null;

    private $lastPoint = null;

    private $rank = 0;

    private $realRank = 0;

    function __construct()
    {
        $mongoConfig = Config::get('database.mongodb');
        $this->mongoClient = MongoClient::create($mongoConfig);
    }

    public static function create()
    {
        return new static();
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

    public function reset()
    {
        $this->reviews = null;
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
        if ($this->reviews === null) {
            $this->reviews = $this->mongoClient->setQueryString(['user_id' => $userId, 'status' => 'active'])->setEndPoint($endPoint)->request('GET');
        }

        return $this->reviews;
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
                if (isset($review->images) && (is_array($review->images) || is_object($review->images))) {
                    foreach($review->images as $image) {
                        if ($image[0]->approval_status === 'approved') {
                            $photos['total_records']++;
                            $photos['records'][] = [
                                'review_id' => $review->_id,
                                'object_id' => $review->object_id,
                                'object_type' => $review->object_type,
                                'store_id' => isset($review->store_id) ? $review->store_id : '',
                                'store_name' => isset($review->store_name) ? $review->store_name : '',
                                'mall_id' => isset($review->location_id) ? $review->location_id : '',
                                'mall_name' => isset($review->mall_name) ? $review->mall_name : '',
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
        $response = $this->mongoClient->setQueryString(['status' => 'active'])->setEndPoint($endPoint)->request('GET');
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
                if (isset($review->images) && (is_array($review->images) || is_object($review->images))) {
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
     * @todo  find a better way to calculate the rank.
     * @param  [type] $userId [description]
     * @return [type]         [description]
     */
    public function getUserRank($userId = null, $totalGamePoints = 0, $leaderboardData = null)
    {
        $userRank = 0;
        $maxRank = 5000;

        /* Dont use cache at the moment.
        Cache::get("ur_{$userId}", null);

        if (! empty($userRank)) {
            return unserialize($userRank);
        }
        */

        if (empty($leaderboardData)) {
            $leaderboardData = $this->getTopRankUsers();
        }

        $realRank = 0;
        $userRank = 0;
        $lastPoint = null;
        $inTopRank = false;
        foreach($leaderboardData as $index => $data) {
            $realRank++;
            if ($lastPoint !== $data['total_game_points']) {
                $lastPoint = $data['total_game_points'];
                $userRank = $realRank;
            }

            if ($data['user_id'] === $userId) {
                $inTopRank = true;
                break;
            }
        }

        if (! $inTopRank) {
            $tablePrefix = DB::getTablePrefix();
            DB::statement(DB::raw("SET @rownum = 0;"));
            DB::statement(DB::raw("SET @lastPoint = '';"));
            DB::statement(DB::raw("SET @groupedRank = 0;"));
            $userRankData = DB::select(
                DB::raw("
                    SELECT * FROM (
                        SELECT
                            @rownum := @rownum + 1 AS rank,
                            user_id,
                            total_game_points,
                            IF (@lastPoint <> total_game_points, @groupedRank := @rownum, @groupedRank) as grouped_rank,
                            IF (@lastPoint <> total_game_points, @lastPoint := total_game_points, @lastPoint) as lastPoint
                        FROM {$tablePrefix}users
                        JOIN {$tablePrefix}roles on {$tablePrefix}users.user_role_id = {$tablePrefix}roles.role_id
                        where role_name = 'Consumer'
                        and status = 'active'
                        and user_email not like 'guest_%'
                        ORDER BY total_game_points DESC
                    ) as ranking
                    where user_id = " . DB::getPdo()->quote($userId) . "
                ")
            );

            if (count($userRankData) === 1) {
                $userRank = (int) $userRankData[0]->grouped_rank;

                if ($userRank > $maxRank) {
                    $userRank = 0; // means not ranked.
                }
            }
            else {
                $userRank = 0;
            }
        }

        return $userRank;
    }

    /**
     * Get user profile.
     *
     * @param  [type] $userId [description]
     * @return [type]         [description]
     */
    public function getUserProfile($userId = null, $leaderboardData = [])
    {
        $userProfile = null;
        $user = User::select(
                    'user_id',
                    DB::raw("CONCAT(user_firstname, ' ', user_lastname) as name"),
                    'total_game_points',
                    'users.created_at',
                    'users.status'
                )
                ->with([
                    'userdetail' => function($userDetail) {
                        $userDetail->select('user_id', 'user_detail_id', 'about', DB::raw('location as profile_location'));
                    },
                    'purchases' => function($purchases) {
                        $purchases->select(
                            DB::raw("count(payment_transaction_id) as number_of_purchases"),
                            'payment_transaction_id',
                            'user_id'
                        )
                        ->where('status', PaymentTransaction::STATUS_SUCCESS)
                        ->groupBy('user_id');
                    },
                    'profilePicture' => function($profilePicture) {
                        $profilePicture->where('media_name_long', 'user_profile_picture_resized_default');
                    },
                ])
                ->join('roles', 'users.user_role_id', '=', 'roles.role_id')
                ->where('roles.role_name', 'Consumer')
                ->whereIn('status', ['active', 'pending'])
                ->where('user_id', $userId)
                ->first();

        if (! empty($user)) {
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');

            $picture = '';
            if ($user->profilePicture->count() > 0) {
                $profilePicture = $user->profilePicture->first();
                $localPath = $profilePicture->path;
                $cdnPath = $profilePicture->cdn_url;

                $picture = $imgUrl->getImageUrl($localPath, $cdnPath);
            }

            $numberOfPurchases = 0;
            if ($user->purchases->count() > 0) {
                $numberOfPurchases = (int) $user->purchases->first()->number_of_purchases;
            }

            $userProfile = (object) [
                'user_id' => $userId,
                'name' => $user->name,
                'location' => $user->userdetail->profile_location,
                'join_date' => $user->created_at->format('Y-m-d H:i:s'),
                'about' => $user->userdetail->about,
                'rank' => 0,
                'total_points' => (int) $user->total_game_points,
                'total_game_points' => (int) $user->total_game_points,
                'number_of_purchases' => $numberOfPurchases,
                'total_reviews' => 0,
                'total_photos' => 0,
                'total_following' => 0,
                'total_purchases' => $numberOfPurchases,
                'picture' => $picture,
                'status' => $user->status,
            ];

            // Get user-related-content total value..
            $userProfile->total_reviews = $this->getTotalReview($userId);
            $userProfile->total_photos = $this->getTotalPhotos($userId);
            $userProfile->total_following = $this->getTotalFollowing($userId);

            // Get user rank.
            $userProfile->rank = $this->getUserRank($userId, $user->total_game_points, $leaderboardData);
        }

        return $userProfile;
    }

    /**
     * Top Rank Users.
     *
     * @param  integer $topRankLimit [description]
     * @return [type]                [description]
     */
    public function getTopRankUsers($topRankLimit = 50)
    {
        /* Dont use cache at the moment.
        $topRankUsers = Cache::get('leaderboard', null);

        if (! empty($topRankUsers)) {
            return unserialize($topRankUsers);
        }
        */
        $topRankUsers = User::select('user_id', DB::raw("CONCAT(user_firstname, ' ', user_lastname) as name"), 'total_game_points')
                ->with([
                    'purchases' => function($purchases) {
                        $purchases->select(
                            'user_id',
                            DB::raw("count(payment_transaction_id) as number_of_purchases")
                        )
                        ->where('status', PaymentTransaction::STATUS_SUCCESS)
                        ->groupBy('user_id');
                    },
                    'profilePicture' => function($profilePicture) {
                        $profilePicture->where('media_name_long', 'user_profile_picture_resized_default');
                    },
                ])
                ->join('roles', 'users.user_role_id', '=', 'roles.role_id')
                ->where('user_email', 'not like', 'guest_%')
                ->where('roles.role_name', 'Consumer')
                ->where('status', 'active')
                ->orderBy('total_game_points', 'desc')
                ->limit($topRankLimit)
                ->get();

        $cdnConfig = Config::get('orbit.cdn');
        $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');

        $topRankUsers->each(function($topRankUser) use ($imgUrl) {
            $this->realRank++;
            $this->reset();
            $profileTotal = $this->getTotalContent($topRankUser->user_id);
            $topRankUser->total_reviews = $profileTotal->reviews;
            $topRankUser->total_photos = $profileTotal->photos;
            $topRankUser->total_following = $profileTotal->following;
            $topRankUser->total_purchases = 0;
            $topRankUser->rank = 0;

            if ($topRankUser->purchases->count() > 0) {
                $topRankUser->total_purchases = $topRankUser->purchases->first()->number_of_purchases;
            }

            unset($topRankUser->purchases);

            // picture
            $topRankUser->picture = '';
            if ($topRankUser->profilePicture->count() > 0) {
                $profilePicture = $topRankUser->profilePicture->first();
                $localPath = $profilePicture->path;
                $cdnPath = $profilePicture->cdn_url;

                $topRankUser->picture = $imgUrl->getImageUrl($localPath, $cdnPath);
            }

            unset($topRankUser->profilePicture);

            if ($topRankUser->total_game_points !== $this->lastPoint) {
                $this->lastPoint = $topRankUser->total_game_points;
                $this->rank = $this->realRank;
            }

            $topRankUser->rank = $this->rank;
        });

        return $topRankUsers->toArray();
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
