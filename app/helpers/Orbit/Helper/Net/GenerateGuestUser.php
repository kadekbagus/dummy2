<?php namespace Orbit\Helper\Net;
/**
 * Helper for generating guest user
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \DB;
use \Config;
use \User;
use \Exception;
use App;
use Mall;
use Activity;
use UserSignin;
use Carbon\Carbon;

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
            if (is_null($session)) {
                throw new Exception("Session not found.", 1);
            }

            $guest_email = $session->read('guest_email');
            $guest = User::excludeDeleted()->where('user_email', $guest_email)->first();
            if (! is_object($guest)) {
                DB::beginTransaction();
                $user = (new User)->generateGuestUser();

                // Start the orbit session
                $data = array(
                    'logged_in' => TRUE,
                    'guest_user_id' => $user->user_id,
                    'guest_email' => $user->user_email,
                    'role'      => $user->role->role_name,
                    'fullname'  => '',
                );
                $session->enableForceNew()->start($data);
                DB::commit();
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

            OrbitInput::get('mall_id', function($mid) use (&$mall_id) {
                $mall_id = $mid;
            });

            OrbitInput::post('mall_id', function($mid) use (&$mall_id) {
                $mall_id = $mid;
            });

            $mall = Mall::with('timezone')->excludeDeleted()->where('merchant_id', $mall_id)->first();

            $start_date = Carbon::now($mall->timezone->timezone_name)->format('Y-m-d 00:00:00');
            $end_date = Carbon::now($mall->timezone->timezone_name)->format('Y-m-d 23:59:59');
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
                DB::commit();
            }


            return $user;

        } catch (Exception $e) {
            DB::rollback();
            if(Config::get('app.debug')) {
                return $e->getMessage() . ' | '  . $e->getLine();
            }

            return $e->getMessage();
        }
    }
}
