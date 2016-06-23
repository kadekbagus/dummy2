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
use Orbit\Helper\Net\SessionPreparer;

class GenerateGuestUser
{   
    /**
     * Generate the guest user
     * If there are already guest_email on the cookie use that email instead
     *
     */
    public static function generateGuestUser($session = NULL)
    {
        try{
            $guest_email = NULL;
            if (! is_null($session)) {
                $guest_email = $session->read('guest_email');
            }
            if (! empty($guest_email)) {
                $guest = User::excludeDeleted()->where('user_email', $guest_email)->first();
                if (! is_object($guest)) {
                    DB::beginTransaction();
                    $user = (new User)->generateGuestUser();
                    DB::commit();
                } else {
                    $user = $guest;
                }
            } else {
                DB::beginTransaction();
                $user = (new User)->generateGuestUser();
                DB::commit();
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
