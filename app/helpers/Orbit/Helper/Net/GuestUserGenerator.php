<?php namespace Orbit\Helper\Net;
/**
 * Helper for generating guest user.
 *
 * @author Ahmad <ahmad@dominopos.com>
 * @author Rio Astamal <rio@domminopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Helper\Net\SignInRecorder;
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
    protected $recordSignInActivity = TRUE;

    /**
     * Guest email address.
     *
     * @var string
     */
    protected $guestEmail = NULL;

    /**
     * User session.
     *
     * @var Session
     */
    protected $session = NULL;

    /**
     * Constructor config: ['guest_email', 'record_signin_activity']
     *
     * @param array $config
     * @return void
     */
    public function __construct($config=[])
    {
        $this->guestEmail = isset($config['guest_email']) ? $config['guest_email'] : $this->guestEmail;
        $this->session = isset($config['session']) ? $config['session'] : $this->session;
        $this->recordSignInActivity = isset($config['record_signin_activity']) ? $config['record_signin_activity'] : $this->recordSignInActivity;
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
        if (! empty($this->guestEmail)) {
            $user = User::excludeDeleted()
                         ->where('user_email', $this->guestEmail)
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

        $mall = NULL;

        $mall_id = App::make('orbitSetting')->getSetting('current_retailer');
        $mall_id = $mall_id === '-' ? NULL : $mall_id;

        OrbitInput::get('mall_id', function($mid) use (&$mall_id) {
            $mall_id = $mid;
        });

        OrbitInput::post('mall_id', function($mid) use (&$mall_id) {
            $mall_id = $mid;
        });

        if (! empty($mall_id)) {
            // request is coming from mobile_ci or desktop_ci
            $mall = Mall::with('timezone')
                ->excludeDeleted()
                ->where('merchant_id', $mall_id)
                ->first();

            $mall_id = is_object($mall) ? $mall_id : NULL;
        }

        $start_date = date('Y-m-d 00:00:00');
        $end_date = date('Y-m-d 23:59:59');
        // check if the guest user UserSignin is already recorded for this day
        $userSignin = UserSignin::where('user_id', '=', $user->user_id)
                                ->where('location_id', $mall_id)
                                ->where('user_signin.created_at', '>=', $start_date)
                                ->where('user_signin.created_at', '<=', $end_date)
                                ->first();

        // record guest login_ok activity and UserSignin
        if (! is_object($userSignin)) {
            if (! is_null($this->session)) {
                // update the visited location in session data to prevent double activity
                $visited_locations = [];
                if (! empty($this->session->read('visited_location'))) {
                    $visited_locations = $this->session->read('visited_location');
                }
                // do not insert user sign in if the location is already visited
                if (! in_array($mall_id, $visited_locations)) {
                    SignInRecorder::setSignInActivity($user, 'guest', $mall, NULL, TRUE);
                    $this->session->write('visited_location', array_merge($visited_locations, [$mall_id]));
                }
            }
        }

        return $user;
    }
}
