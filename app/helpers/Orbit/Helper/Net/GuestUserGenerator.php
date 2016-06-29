<?php namespace Orbit\Helper\Net;
/**
 * Helper for generating guest user.
 *
 * @author Ahmad <ahmad@dominopos.com>
 * @author Rio Astamal <rio@domminopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use DB;
use Config;
use User;
use Exception;
use App;
use Mall;
use Activity;
use UserSignin;
use Carbon\Carbon;

class GuestUserGenerator
{
    /**
     * Flag for recording the gust user sign in to activity or not.
     *
     * @var boolean
     */
    protected $recordSignInActivity = FALSE;

    /**
     * Guest email address.
     *
     * @var string
     */
    protected $guestEmail = NULL;

    /**
     * Constructor config: ['guest_email', 'record_signin_activity']
     *
     * @param array $config
     * @return void
     */
    public function __construct($config=[])
    {
        $this->guestEmail = isset($config['guest_email']) ? $config['guest_email'] : $this->guestEmail;
        $this->recordSignInActivity = isset($config['record_signin_activity']) ? $config['record_signin_activity'] : $this->guestEmail;
    }

    /**
     * @param array $config
     * @return GuestUserGenerator
     */
    public static function create($config=[])
    {
        return new static($config);
    }

    /**
     * Generate the guest user
     * If there are already guest_email on the cookie use that email instead
     *
     * @return User|mixed
     */
    public function generate()
    {
        if (! empty($guest_email)) {
            $user = User::excludeDeleted()
                         ->where('user_email', $guest_email)
                         ->first();

            if (! is_object($user)) {
                DB::beginTransaction();
                $user = (new User)->generateGuestUser();
                DB::commit();
            }
        } else {
            DB::beginTransaction();
            $user = (new User)->generateGuestUser();
            DB::commit();
        }

        if (! $this->recordSignInActivity) {
            return $user;
        }

        // todo: add login_ok activity
        $mall_id = App::make('orbitSetting')->getSetting('current_retailer');

        OrbitInput::get('mall_id', function($mid) use (&$mall_id) {
            $mall_id = $mid;
        });

        OrbitInput::post('mall_id', function($mid) use (&$mall_id) {
            $mall_id = $mid;
        });

        $mall = Mall::with('timezone')
                    ->excludeDeleted()
                    ->where('merchant_id', $mall_id)
                    ->first();

        $start_date = Carbon::now($mall->timezone->timezone_name)->format('Y-m-d 00:00:00');
        $end_date = Carbon::now($mall->timezone->timezone_name)->format('Y-m-d 23:59:59');
        $userSignin = UserSignin::where('user_id', '=', $user->user_id)
                                ->where('location_id', $mall_id)
                                ->whereBetween('user_signin.created_at', [$start_date, $end_date])
                                ->first();

        if (! is_object($userSignin)) {
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
    }
}
