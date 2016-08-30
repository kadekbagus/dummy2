<?php
/**
 * Intermediate Controller for all angular CI controller which need authentication.
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use OrbitShop\API\v1\ResponseProvider;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use Orbit\Helper\Net\UrlChecker;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Net\GuestUserGenerator;

class IntermediateCIAuthController extends IntermediateBaseController
{
    const APPLICATION_ID = 1;
    /**
     * Check the authenticated user on constructor
     *
     * @author Ahmad <ahnad@dominopos.com>
     */
    public function __construct()
    {
        parent::__construct();

        $this->beforeFilter(function()
        {
            try
            {
                $this->session = SessionPreparer::prepareSession();
                $user = $this->getLoggedInUser($this->session);
                $guest = $this->getLoggedInGuest($this->session);
                if (! is_object($user)) {
                    $user = $guest;
                }
                UrlChecker::checkBlockedUrl($user);
            } catch (ACLForbiddenException $e) {
                $response = new ResponseProvider();
                $response->code = $e->getCode();
                $response->status = 'error';
                $response->message = $e->getMessage();

                return $this->render($response);
            } catch (Exception $e) {
                $response = new ResponseProvider();
                $response->code = $e->getCode();
                $response->status = 'error';
                $response->message = $e->getMessage();

                return $this->render($response);
            }
        });
    }

    /**
     * Get current logged in user used in view related page.
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @return User $user
     */
    protected function getLoggedInUser($session)
    {
        $userId = $session->read('user_id');

        if ($session->read('logged_in') !== true || ! $userId) {
            // throw new Exception('Invalid session data.');
        }

        // @todo: Why we query membership also? do we need it on every page?
        $user = User::with('userDetail')
            ->where('user_id', $userId)
            ->whereHas('role', function($q) {
                $q->where('role_name', 'Consumer');
            })
            ->first();

        if (! is_object($user)) {
            $user = NULL;
            // throw new Exception('Session error: user not found.');
        }

        return $user;
    }

    /**
     * Get current logged in guest used in view related page.
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @return User $user
     */
    protected function getLoggedInGuest($session)
    {
        $userId = $session->read('guest_user_id');

        $generateGuest = function ($session) {
            $user = GuestUserGenerator::create()->generate();

            $sessionData = $session->read(NULL);
            $sessionData['logged_in'] = TRUE;
            $sessionData['guest_user_id'] = $user->user_id;
            $sessionData['guest_email'] = $user->user_email;
            $sessionData['role'] = $user->role->role_name;
            $sessionData['fullname'] = '';

            $session->update($sessionData);

            return $user;
        };

        if (! empty($userId)) {
            $user = User::with('userDetail')
                ->where('user_id', $userId)
                ->whereHas('role', function($q) {
                    $q->where('role_name', 'guest');
                })
                ->first();

            if (! is_object($user)) {
                $user = $generateGuest($session);
            }
        } else {
            $user = $generateGuest($session);
        }

        return $user;
    }
}
