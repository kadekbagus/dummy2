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
use App;
use Mall;
use Activity;
use UserSignin;

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

                // Send the session id via HTTP header
                $sessionHeader = $session->getSessionConfig()->getConfig('session_origin.header.name');
                $sessionHeader = 'Set-' . $sessionHeader;
                $customHeaders = array();
                $customHeaders[$sessionHeader] = $session->getSessionId();
            } else {
                $user = $guest;
            }

            // todo: add login_ok activity
            $mall_id = App::make('orbitSetting')->getSetting('current_retailer');
            $mall = Mall::with('timezone')->excludeDeleted()->where('merchant_id', $mall_id)->first();
            dd($mall);

            $start_date = '2016-06-01 17:00:00';
            $end_date = '2016-06-02 16:59:59';
            $userSignin = UserSignin::where('user_id', '=', $user->user_id)
                                    ->where('location_id', $mall_id)
                                    ->whereBetween('user_signin.created_at', [$start_date, $end_date])
                                    ->first();

            if (!is_object($userSignin)) {
                DB::beginTransaction();
                $activity = Activity::mobileci()
                        ->setLocation($mall)
                        ->setUser($user)
                        ->setActivityName('login_ok')
                        ->setActivityNameLong('Sign In')
                        ->setActivityType('login')
                        ->setObject($user)
                        ->setModuleName('Application')
                        ->responseOK();

                $activity->save();

                $newUserSignin = new UserSignin();
                $newUserSignin->user_id = $user->user_id;
                $newUserSignin->signin_via = 'guest';
                $newUserSignin->location_id = $mall_id;
                $newUserSignin->activity_id = $activity->activity_id;
                $newUserSignin->save();
            }

            DB::commit();

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
