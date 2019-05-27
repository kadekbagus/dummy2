<?php namespace Orbit\Controller\API\v1\Pub;

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
use Orbit\Helper\Util\CdnUrlGenerator;
use PaymentTransaction;
use Queue;
use User;
use UserDetail;
use Validator;
use stdclass;
use Cache;
use Orbit\Controller\API\v1\Pub\UserProfile\ProfileHelper;

/**
 * Handler for leaderboard.
 *
 * @author Budi <budi@dominopos.com>
 */
class LeaderboardAPIController extends PubControllerAPI
{
    private $lastPoint = null;

    public function getLeaderboardData()
    {
        $httpCode = 200;
        $this->response->data = null;
        $this->response->code = 0;
        $this->response->status = 'success';
        $this->response->message = 'Request OK';

        try {
            $user = $this->getUser();
            $role = strtolower($user->role->role_name);

            $fromCache = OrbitInput::get('from_cache', 'N');
            $topRankLimit = Config::get('orbit.gamification.leaderboard.max_record', 50);

            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');
            $profileHelper = new profileHelper();

            /* Dont use cache at the moment, let it re-calculate on every request.
            $leaderboardData = Cache::get('leaderboard', null);

            if (empty($leaderboardData)) {
                // Can be set to do calculation here, but it will take some time.
                return $this->render($httpCode);
            }
            */

            // Loop thru leaderboard data, and see if current user
            // in in top 50. Only calculate if user logged in.
            $leaderboardData = $profileHelper->getTopRankUsers();
            if ($role === 'consumer') {
                $inTopRank = false;
                foreach($leaderboardData as $index => $data) {
                    if ($data['user_id'] === $user->user_id) {
                        $leaderboardData[$index]['highlight'] = true;
                        $inTopRank = true;
                        Cache::put("ur_{$user->user_id}", serialize($data['rank']), 60);
                        break;
                    }
                }

                // If not in top 50, then calculate user rank...
                // Get from cache first...
                if (! $inTopRank) {
                    $userProfile = Cache::get("up_{$user->user_id}", function() use ($profileHelper, $leaderboardData, $user) {
                        $profile = $profileHelper->getUserProfile($user->user_id, $leaderboardData);

                        return $profile;
                    });


                    // Put in cache
                    Cache::put("ur_{$user->user_id}", serialize($userProfile->rank), 60);

                    unset($userProfile->total_points);
                    unset($userProfile->number_of_purchases);

                    $leaderboardData[] = $userProfile;
                }
            }

            $data = new \stdClass;
            $data->total_records = count($leaderboardData);
            $data->records = $leaderboardData;

            $this->response->data = $data;

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

    public function setResponseData($data = [])
    {
        $this->response->data = $data;
        return $this;
    }
}
