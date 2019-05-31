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
use Orbit\Helper\Util\CdnUrlGenerator;
use PaymentTransaction;
use Queue;
use User;
use UserDetail;
use Validator;
use stdclass;
use Cache;

/**
 * Handler for user profile, both for himself or other user profile.
 *
 * @author Budi <budi@dominopos.com>
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

            $profile = $profileHelper->getUserProfile($beingViewedUserId);

            if (empty($profile)) {
                $errorMessage = 'USER_NOT_FOUND';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Store in cache
            // Cache::put("up_{$beingViewedUserId}", serialize($profile), 60);

            unset($profile->total_game_points);
            unset($profile->total_purchases);

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
