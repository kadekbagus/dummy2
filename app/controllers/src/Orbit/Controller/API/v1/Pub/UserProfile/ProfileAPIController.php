<?php namespace Orbit\Controller\API\v1\Pub\UserProfile;

use Activity;
use Config;
use DB;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Event;
use Hash;
use Lang;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Util\CdnUrlGenerator;
use Orbit\Models\Gamification\UserVariable;
use PaymentTransaction;
use Queue;
use User;
use UserDetail;
use Validator;
use stdclass;
use Cache;

/**
 * Handler for user profile, both for himself or other user profile.
 */
class ProfileAPIController extends PubControllerAPI
{
    public function getUserProfile()
    {
        $httpCode = 200;
        $this->response->data = null;
        $this->response->code = 0;
        $this->response->status = 'success';
        $this->response->message = 'Request OK';

        try {
            $user = $this->getUser();
            $role = strtolower($user->role->role_name);

            $beingViewedUserId = OrbitInput::get('view_user_id', $user->user_id);
            $fromCache = OrbitInput::get('from_cache', 'N');
            $skip = OrbitInput::get('skip', 0);
            $take = OrbitInput::get('take', 10);
            $profileHelper = new ProfileHelper();

            // Return data null if accessed by guest
            if ($beingViewedUserId === $user->user_id && $role === 'guest') {
                return $this->render($httpCode);
            }

            $prefix = DB::getTablePrefix();
            $profile = null;

            // Get from cache...
            if ($fromCache === 'Y') {
                $profile = Cache::get("up_{$beingViewedUserId}", null);
                if (! empty($profile)) {
                    $this->response->data = unserialize($profile);
                    return $this->render($httpCode);
                }
            }

            $beingViewedUser = User::with([
                'userdetail' => function($userDetail) {
                    $userDetail->select('user_id', 'user_detail_id', 'about', 'location');
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
            ])
            ->where('user_id', $beingViewedUserId)
            ->first();

            if (empty($beingViewedUser)) {
                $errorMessage = 'USER_NOT_FOUND';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $profile = (object) [
                'user_id' => $beingViewedUser->user_id,
                'name' => $beingViewedUser->user_firstname . ' ' . $beingViewedUser->user_lastname,
                'location' => $beingViewedUser->userdetail->location,
                'join_date' => $beingViewedUser->created_at->format('Y-m-d H:i:s'),
                'about' => $beingViewedUser->userdetail->about,
                'rank' => 0,
                'total_points' => (int) $beingViewedUser->total_game_points,
                'number_of_purchases' => 0,
                'total_reviews' => 0,
                'total_photos' => 0,
                'total_following' => 0,
            ];

            if ($beingViewedUser->purchases->count() > 0) {
                $profile->number_of_purchases = (int) $beingViewedUser->purchases->first()->number_of_purchases;
            }

            // Get user rank.
            $profile->rank = $profileHelper->getUserRank($beingViewedUserId)->grouped_rank;

            // Get user-related-content total value..
            $profileTotal = $profileHelper->getTotalContent($beingViewedUserId);
            $profile->total_reviews = $profileTotal->reviews;
            $profile->total_photos = $profileTotal->photos;
            $profile->total_following = $profileTotal->following;

            // Store in cache
            Cache::put("up_{$beingViewedUserId}", serialize($profile), 60);

            $this->response->data = $profile;

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }
}
