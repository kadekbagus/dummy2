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

class BaseCIController extends ControllerAPI
{
    const APPLICATION_ID = 1;
    const PAYLOAD_KEY = '--orbit-mall--';
    protected $session = null;
    protected $retailer = null;

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
            throw new Exception('Invalid session data.');
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
            }])->where('user_id', $userId)->first();

        if (! $user) {
            throw new Exception('Session error: user not found.');
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
     * @return void
     */
    protected function prepareSession()
    {
        if (! is_object($this->session)) {
            // This user assumed are Consumer, which has been checked at login process
            $config = new SessionConfig(Config::get('orbit.session'));
            $config->setConfig('application_id', static::APPLICATION_ID);

            try {
                $this->session = new Session($config);
                $this->session->start();
            } catch (Exception $e) {
                Redirect::to('/customer/logout');
            }
        }
    }

    /**
     * Redirect user if not logged in to sign page
     *
     * @param object $e - Error object
     *
     * @return Illuminate\Support\Facades\Redirect
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function redirectIfNotLoggedIn($e)
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
                return \Redirect::to('/customer/logout');
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
}