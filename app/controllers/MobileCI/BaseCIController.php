<?php namespace MobileCI;
/**
 * @author Rio Astamal <riO@dominopos.com>
 * @desc Base controller used for Mobile CI
 */
use OrbitShop\API\v1\ControllerAPI;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Config;
use \Exception;
use User;
use App;
use Mall;
use Setting;
use MerchantLanguage;
use \Redirect;
use Orbit\Helper\Net\UrlChecker as UrlBlock;

class BaseCIController extends ControllerAPI
{
    const APPLICATION_ID = 1;
    const PAYLOAD_KEY = '--orbit-mall--';
    protected $session = null;
    protected $retailer = null;
    protected $commonViewsData = [];
    protected $pageTitle = '';

    /**
    * Get list language from current merchant or mall
    *
    * @param mall     `mall`    mall object
    *
    * @author Firmansyah <firmansyah@dominopos.com>
    * @author Irianto Pratama <irianto@dominopos.com>
    *
    * @return array or collection
    */
    protected function getListLanguages($mall)
    {
        $languages = MerchantLanguage::with('language')
                                     ->join('languages', 'languages.language_id', '=','merchant_languages.language_id')
                                     ->where('merchant_languages.status', '!=', 'deleted')
                                     ->where('merchant_id', $mall->merchant_id)
                                     ->where('languages.status', 'active')
                                     ->orderBy('languages.name_long', 'ASC')
                                     ->get();

        return $languages;
    }

    /**
     * Get current logged in user used in view related page.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return User $user
     */
    protected function getLoggedInUser()
    {
        $this->prepareSession();

        $userId = $this->session->read('user_id');

        if ($this->session->read('logged_in') !== true || ! $userId) {
            // throw new Exception('Invalid session data.');
        }

        if (empty($this->retailer)) {
            $this->retailer = $this->getRetailerInfo();
        }
        $retailer = $this->retailer;

        // @todo: Why we query membership also? do we need it on every page?
        $user = User::with(['userDetail',
            'membershipNumbers' => function($q) use ($retailer) {
                $q->select('membership_numbers.*')
                    ->with('membership.media')
                    ->join('memberships', 'memberships.membership_id', '=', 'membership_numbers.membership_id')
                    ->excludeDeleted('membership_numbers')
                    ->excludeDeleted('memberships')
                    ->where('memberships.merchant_id', $retailer->merchant_id);
            }])
            ->where('user_id', $userId)
            ->whereHas('role', function($q) {
                $q->where('role_name', 'Consumer');
            })
            ->first();

        if (! $user) {
            $user = NULL;
            // throw new Exception('Session error: user not found.');
        } else {
            $_user = clone($user);
            if (count($_user->membershipNumbers)) {
               $user->membership_number = $_user->membershipNumbers[0]->membership_number;
            }
        }

        return $user;
    }

    /**
     * GET - Get current active mall
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return \Mall
     */
    public function getRetailerInfo($with = null)
    {
        try {
            $retailer_id = App::make('orbitSetting')->getSetting('current_retailer');

            $retailer = Mall::with('parent')->where('merchant_id', $retailer_id);
            if (! is_null($with)) {
                $with = (array) $with;
                foreach ($with as $rel) {
                    $retailer->with($rel);
                }
            }
            $retailer = $retailer->first();

            $membership_card = Setting::where('setting_name','enable_membership_card')->where('object_id',$retailer_id)->first();

            if (! empty($membership_card)){
                $retailer->enable_membership=$membership_card->setting_value;
            } else {
                $retailer->enable_membership='false';
            }

            return $retailer;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
    }

    /**
     * Prepare session.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @return void
     */
    protected function prepareSession()
    {
        if (! is_object($this->session)) {
            // set the session strict to FALSE
            Config::set('orbit.session.strict', FALSE);
            // set the query session string to FALSE, so the CI will depend on session cookie
            Config::set('orbit.session.availability.query_string', FALSE);

            // This user assumed are Consumer, which has been checked at login process
            $config = new SessionConfig(Config::get('orbit.session'));
            $config->setConfig('application_id', static::APPLICATION_ID);

            try {
                $this->session = new Session($config);
                $this->session->start(array(), 'no-session-creation');
            } catch (Exception $e) {
                $this->session->start();
            }
        }
    }

    /**
     * Redirect user if not logged in to sign page
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Rio Astamal <rio@dominopos.com>
     * @param object $e - Error object
     * @param string $urlLogout - Redirect to URL
     * @return Illuminate\Support\Facades\Redirect
     */
    public function redirectIfNotLoggedIn($e, $urlLogout='/customer/logout')
    {
        if (Config::get('app.debug')) {
            return $e;
        }

        switch ($e->getCode()) {
            case Session::ERR_UNKNOWN;
            case Session::ERR_IP_MISS_MATCH;
            case Session::ERR_UA_MISS_MATCH;
            case Session::ERR_SESS_NOT_FOUND;
            case Session::ERR_SESS_EXPIRE;
                return \Redirect::to($urlLogout);
                break;

            default:
                return \Redirect::to('/customer');
        }
    }

    public function base64UrlEncode($inputStr)
    {
        return strtr(base64_encode($inputStr), '+/=', '-_,');
    }

    public function base64UrlDecode($inputStr)
    {
        return base64_decode(strtr($inputStr, '-_,', '+/='));
    }

    /**
     * $user is from getLoggedInUser() and it may returns
     * NULL user. This method is safety net to make sure
     * that email otherwise valid or empty
     * @param type $user
     * @return user email or empty if not valid
     */
    private function getUserEmail($user) {
        $user_email = '';
        if (isset($user) && isset($user->role)) {
            $user_email = $user->role->role_name !== 'Guest' ? $user->user_email : '';
        }
        return $user_email;
    }

    /**
     * This method return list of data which mostly needed by views.
     *
     * @return array
     */
    protected function fillCommonViewsData()
    {
        $retailer = $this->getRetailerInfo();
        $languages = $this->getListLanguages($retailer);
        $user = $this->getLoggedInUser();

        $urlblock = new UrlBlock('no-prepare-session');
        $urlblock->setUserSession($this->session);

        return [
            'user' => $user,
            'retailer' => $retailer,
            'urlblock' => $urlblock,
            'languages' => $languages,
            'page_title' => $this->pageTitle,
            'user_email' => $this->getUserEmail($user)
        ];
    }
}