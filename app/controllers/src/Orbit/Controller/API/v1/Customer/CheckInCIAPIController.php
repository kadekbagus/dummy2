<?php namespace Orbit\Controller\API\v1\Customer;

/**
 * @author Firmansyah <firmansyah@dominopos.com>
 * @desc Controller for check login user
 */
use Orbit\Controller\API\v1\Customer\BaseAPIController;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Orbit\Helper\Net\SessionPreparer;
use \DB;
use \Validator;
use Mall;
use Setting;
use App;
use Lang;
use Carbon\Carbon as Carbon;
use Activity;
use UserSignin;
use DominoPOS\OrbitSession\Session as OrbitSession;
use DominoPOS\OrbitSession\SessionConfig;
use Orbit\Helper\Session\AppOriginProcessor;
use Orbit\Helper\Session\UserGetter;



class CheckInCIAPIController extends BaseAPIController
{
    protected $validRoles = ['super admin', 'consumer', 'guest'];
    protected $mall_id = NULL;

    public function postCekSignIn()
    {
        $mallId = OrbitInput::get('mall_id', null);
        $user = null;
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try {
            if (!empty($mallId)) {
                $this->registerCustomValidation();

                $validator = Validator::make(
                    array(
                        'mall_id' => $mallId,
                    ),
                    array(
                        'mall_id' => 'orbit.empty.mall',
                    )
                );

                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $retailer = Mall::excludeDeleted()->where('merchant_id', $mallId)->first();

                // Return mall_portal, cs_portal, pmp_portal etc
                $this->appOrigin = AppOriginProcessor::create(Config::get('orbit.session.app_list'))
                                               ->getAppName();

                // Session Config
                $orbitSessionConfig = Config::get('orbit.session.origin.' . $this->appOrigin);
                $applicationId = Config::get('orbit.session.app_id.' . $this->appOrigin);

                // Instantiate the OrbitSession object
                $sessConfig = new SessionConfig(Config::get('orbit.session'));
                $sessConfig->setConfig('session_origin', $orbitSessionConfig);
                $sessConfig->setConfig('expire', $orbitSessionConfig['expire']);
                $sessConfig->setConfig('application_id', $applicationId);

                $this->session = new OrbitSession($sessConfig);
                $this->session->start([], 'no-session-creation');

                $user = UserGetter::getLoggedInUserOrGuest($this->session);

                if (is_object($user)) {
                    $this->acquireUser($retailer, $user);
                }

                $this->response->data = null;
                $this->response->code = 0;
                $this->response->status = 'success';
                $this->response->message = 'Success';
            }
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }


    protected function acquireUser($retailer, $user, $signUpVia = null)
    {
        $session = $this->session;
        if (is_null($signUpVia)) {
            $signUpVia = 'form';
            if (isset($_COOKIE['login_from'])) {
                switch (strtolower($_COOKIE['login_from'])) {
                    case 'google':
                        $signUpVia = 'google';
                        break;
                    case 'facebook':
                        $signUpVia = 'facebook';
                        break;
                    default:
                        $signUpVia = 'form';
                        break;
                }
            } else {
                if (! empty($session->read('login_from'))) {
                    switch (strtolower($session->read('login_from'))) {
                        case 'google':
                            $signUpVia = 'google';
                            break;
                        case 'facebook':
                            $signUpVia = 'facebook';
                            break;
                        default:
                            $signUpVia = 'form';
                            break;
                    }
                }
            }

            $signUpVia = $user->isGuest() ? 'guest' : $signUpVia;
        }

        if ($user->isConsumer()) {
            $firstAcquired = $retailer->acquireUser($user, $signUpVia);

            // if the user is viewing the mall for the 1st time then set the signup activity
            if ($firstAcquired) {
                $this->setSignUpActivity($user, $signUpVia, $retailer);
            }
        }

        $visited_locations = [];
        if (! empty($session->read('visited_location'))) {
            $visited_locations = $session->read('visited_location');
        }
        if (! in_array($retailer->merchant_id, $visited_locations)) {
            $this->setSignInActivity($user, $signUpVia, $retailer, null);
            $session->write('visited_location', array_merge($visited_locations, [$retailer->merchant_id]));
        }
    }

    // create activity signup from socmed
    public function setSignUpActivity($user, $from, $retailer)
    {
        $activity = Activity::mobileci()
            ->setLocation($retailer)
            ->setActivityType('registration')
            ->setUser($user)
            ->setActivityName('registration_ok')
            ->setObject($user)
            ->setModuleName('User')
            ->responseOK();

        if ($from === 'facebook') {
            $activity->setActivityNameLong('Sign Up via Mobile (Facebook)')
                    ->setNotes('Sign Up via Mobile (Facebook) OK');
        } else if ($from === 'google') {
            $activity->setActivityNameLong('Sign Up via Mobile (Google+)')
                    ->setNotes('Sign Up via Mobile (Google+) OK');
        } else if ($from === 'form') {
            $activity->setActivityNameLong('Sign Up via Mobile (Email Address)')
                    ->setNotes('Sign Up via Mobile (Email Address) OK');
        }

        $activity->save();
    }

    // create activity signin from socmed
    public function setSignInActivity($user, $from, $retailer, $activity = null)
    {
        if (is_object($user)) {
            if (is_null($activity)) {
                $activity = Activity::mobileci()
                        ->setLocation($retailer)
                        ->setUser($user)
                        ->setActivityName('login_ok')
                        ->setActivityNameLong('Sign In')
                        ->setActivityType('login')
                        ->setObject($user)
                        ->setModuleName('Application')
                        ->responseOK();

                $activity->save();
            }

            $newUserSignin = new UserSignin();
            $newUserSignin->user_id = $user->user_id;
            $newUserSignin->signin_via = $from;
            $newUserSignin->location_id = $retailer->merchant_id;
            $newUserSignin->activity_id = $activity->activity_id;
            $newUserSignin->save();
        } else {
            $activity = Activity::mobileci()
                    ->setLocation($retailer)
                    ->setUser('guest')
                    ->setActivityName('login_failed')
                    ->setActivityNameLong('Sign In Failed')
                    ->setActivityType('login')
                    ->setModuleName('Application')
                    ->responseFailed();

            $activity->save();
        }
    }

    protected function registerCustomValidation()
    {
        // Check the existance of merchant id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });
    }
}
