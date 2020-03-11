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
    public static function getLoggedInUser($session, $roles = ['Consumer'])
    {
        $userId = $session->read('user_id');

        // @todo: Why we query membership also? do we need it on every page?
        $user = User::with('userDetail')
            ->where('user_id', $userId)
            ->whereHas('role', function($q) use ($roles) {
                if (! empty($roles)) {
                    $q->whereIn('role_name', $roles);
                }
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

        $generateGuest = function ($session) {
            $guestConfig = [
                'session' => $session
            ];
            $user = GuestUserGenerator::create($guestConfig)->generate();

            $sessionData = $session->read(NULL);
            $sessionData['logged_in'] = TRUE;
            $sessionData['guest_user_id'] = $user->user_id;
            $sessionData['guest_email'] = $user->user_email;
            $sessionData['role'] = $user->role->role_name;
            $sessionData['fullname'] = '';

            $session->update($sessionData);

            return $user;
        };

        $user = User::with('userDetail')
            ->where('user_id', $userId)
            ->whereHas('role', function($q) {
                $q->where('role_name', 'guest');
            })
            ->first();

        if (! is_object($user)) {
            $user = $generateGuest($session);
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
