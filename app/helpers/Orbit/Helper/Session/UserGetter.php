<?php namespace Orbit\Helper\Session;

use Orbit\Helper\Session\AppOriginProcessor;
use Orbit\Helper\Net\GuestUserGenerator;
use User;

/**
 * Helper for getting user by session
 *
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */
class UserGetter
{
    /**
     * Get current logged in user.
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @param DominoPOS\OrbitSession\Session $session
     * @return User $user
     */
    public static function getLoggedInUser($session)
    {
        $userId = $session->read('user_id');

        // @todo: Why we query membership also? do we need it on every page?
        $user = User::with('userDetail')
            ->where('user_id', $userId)
            ->whereHas('role', function($q) {
                $q->where('role_name', 'Consumer');
            })
            ->first();

        if (! is_object($user)) {
            $user = NULL;
        }

        return $user;
    }

    /**
     * Get current logged in guest.
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @param DominoPOS\OrbitSession\Session $session
     * @return User $user
     */
    public static function getLoggedInGuest($session)
    {
        $userId = $session->read('guest_user_id');

        $user = User::with('userDetail')
            ->where('user_id', $userId)
            ->whereHas('role', function($q) {
                $q->where('role_name', 'guest');
            })
            ->first();

        if (! is_object($user)) {
            $user = NULL;
        }

        return $user;
    }

    /**
     * Get current logged in user, if empty user guest user.
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @param DominoPOS\OrbitSession\Session $session
     * @return User $user
     */
    public static function getLoggedInUserOrGuest($session)
    {
        $user = static::getLoggedInUser($session);
        $guest = static::getLoggedInGuest($session);
        if (! is_object($user)) {
            $user = $guest;
        }

        return $user;
    }
}
