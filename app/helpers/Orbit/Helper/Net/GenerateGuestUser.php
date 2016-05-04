<?php namespace Orbit\Helper\Net;
/**
 * Helper for checking url if it is blocked by the config or not
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \DB;
use \Config;
use \User;
use \Exception;

class GenerateGuestUser
{   
    /**
     * Generate the guest user
     * If there are already guest_email on the cookie use that email instead
     *
     */
    public static function generateGuestUser($session)
    {
        try{
            $guest_email = $session->read('guest_email');
            $guest = User::excludeDeleted()->where('user_email', $guest_email)->first();
            if (! is_object($guest)) {
                DB::beginTransaction();
                $user = (new User)->generateGuestUser();
                DB::commit();
                // Start the orbit session
                $data = array(
                    'logged_in' => TRUE,
                    'guest_user_id' => $user->user_id,
                    'guest_email' => $user->user_email,
                    'role'      => $user->role->role_name,
                    'fullname'  => '',
                );
                $session->enableForceNew()->start($data);
                // todo: add login_ok activity

                // Send the session id via HTTP header
                $sessionHeader = $session->getSessionConfig()->getConfig('session_origin.header.name');
                $sessionHeader = 'Set-' . $sessionHeader;
                $customHeaders = array();
                $customHeaders[$sessionHeader] = $session->getSessionId();
            } else {
                $user = $guest;
            }

            return $user;

        } catch (Exception $e) {
            DB::rollback();
            if(Config::get('app.debug')) {
                return print_r([$e->getMessage(), $e->getLine()]);
            }

            return $e->getMessage();
        }
    }
}
