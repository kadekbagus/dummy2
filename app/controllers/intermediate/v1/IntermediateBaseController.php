<?php
/**
 * Base controller class for Intermediate Controller
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitACL\Exception\ACLUnauthenticatedException;
use DominoPOS\OrbitSession\Session as OrbitSession;
use DominoPOS\OrbitSession\SessionConfig;
use Illuminate\Database\QueryException;
use OrbitShop\API\v1\ExceptionResponseProvider;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\Helper\Generator;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\ResponseProvider;
use Orbit\Helper\Session\AppOriginProcessor;
use Orbit\Helper\Util\CorsHeader;

class IntermediateBaseController extends Controller
{
    /**
     * Array of custom headers which sent to client
     *
     * @var array
     */
    protected $customHeaders = array();

    /**
     * Content type of the returned response
     *
     * @var string
     */
    protected $contentType = 'application/json';

    /**
     * Store Orbit Session
     *
     * @var OrbitSession
     */
    protected $session = NULL;

    /**
     * Using transaction or not.
     *
     * @var boolean
     */
    protected $useTransaction = TRUE;

    /**
     * App origin.
     *
     * @var boolean
     */
    protected $appOrigin = NULL;

    /**
     * Array of allowed user roles to access current api/request.
     * Will be used on beforeFilter() hooks to authenticate the user.
     *
     * @var array
     */
    protected $allowedRoles = [];

    /**
     * Class constructor
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    public function __construct()
    {
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

        // CSRF protection
        $csrfProtection = Config::get('orbit.security.csrf.protect');
        App::setLocale(Request::input('language', 'en'));

        if (Request::isMethod('post') && $csrfProtection === TRUE) {
            $csrfMode = Config::get('orbit.security.csrf.mode');

            try
            {
                $token = '';
                switch ($csrfMode) {
                    case 'angularjs':
                        $csrfTokenName = Config::get('orbit.security.csrf.angularjs.header_name_php');

                        // AngularJS send their token via HTTP Header
                        $token = $_SERVER[$csrfTokenName];

                        break;

                    case 'normal':
                    default:
                        $csrfTokenName = Config::get('orbit.security.csrf.normal.name');
                        $token = OrbitInput::post($csrfTokenName);
                }

                if (Session::token() !== $token) {
                    $message = Lang::get('validation.orbit.access.tokenmissmatch');
                    ACL::throwAccessForbidden($message);
                }
            } catch (ACLForbiddenException $e) {
                $response = new ResponseProvider();
                $response->code = $e->getCode();
                $response->status = 'error';
                $response->message = $e->getMessage();

                return $this->render($response);
            } catch (Exception $e) {
                $response = new ResponseProvider();
                $response->code = $e->getCode();
                $response->status = 'error';
                $response->message = $e->getMessage();

                return $this->render($response);
            }
        }
    }

    /**
     * Static method to instantiate the class
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return IntermediateBaseController
     */
    public static function create()
    {
        return new static;
    }

    /**
     * @return array
     */
    protected function getCORSHeaders()
    {
        $cors = CorsHeader::create(Config::get('orbit.security.cors', []));

        // Allow Cross-Domain Request
        // http://enable-cors.org/index.html
        $headers = [];
        $headers['Access-Control-Allow-Origin'] = $cors->getAllowOrigin();
        $headers['Access-Control-Allow-Methods'] = $cors->getAllowMethods();
        $headers['Access-Control-Allow-Credentials'] = $cors->getAllowCredentials();

        $angularTokenName = Config::get('orbit.security.csrf.angularjs.header_name');
        $sessionHeader = $this->session->getSessionConfig()->getConfig('session_origin.header.name');
        $allowHeaders = $cors->getAllowHeaders();
        if (! empty($angularTokenName)) {
            $allowHeaders[] = $angularTokenName;
        }

        $headers['Access-Control-Allow-Headers'] = implode(',', $allowHeaders);
        $headers['Access-Control-Expose-Headers'] = implode(',', $allowHeaders);

        return $headers;
    }

    /**
     * Render the output
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param ResponseProvider $response
     * @return Response
     */
    public function render($response=NULL)
    {
        $output = '';

        if (is_null($response)) {
            $response = new ResponseProvider();
        }

        switch ($this->contentType) {
            case 'application/json':
            default:
                $json = new \stdClass();
                $json->code = $response->code;
                $json->status = $response->status;
                $json->message = $response->message;
                $json->data = $response->data;

                $output = json_encode($json);

                $this->customHeaders = $this->customHeaders + $this->getCORSHeaders();
        }

        $headers = array('Content-Type' => $this->contentType) + $this->customHeaders;

        return Response::make($output, 200, $headers);
    }

    /**
     * Call the real controller API to handle the request.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return mixed
     */
    public function callApi()
    {
        // Get the API key of current user
        $theClass = get_class($this);
        $namespace = '';

        $user = null;
        if ($theClass === 'IntermediateAuthController') {
            if ($userId = $this->authCheck()) {
                $user = User::findOnWriteConnection($userId);

                // This will query the database if the apikey has not been set up yet
                $apikey = $user->apikey;

                if (empty($apikey)) {
                    // Create new one
                    $apikey = $user->createAPiKey();
                }

                // Generate the signature
                $_GET['apikey'] = $apikey->api_key;
                $_GET['apitimestamp'] = time();
                $signature = Generator::genSignature($apikey->api_secret_key);
                $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = $signature;
            }
        } elseif ($theClass === 'IntermediateCIAuthController') {
            $namespace = 'Orbit\Controller\API\v1\Customer\\';
            if ($userId = $this->authCheckFromAngularCI()) {
                $user = User::findOnWriteConnection($userId);
                // This will query the database if the apikey has not been set up yet
                $apikey = $user->apikey;

                if (empty($apikey)) {
                    // Create new one
                    $apikey = $user->createAPiKey();
                }
                // Generate the signature
                $_GET['apikey'] = $apikey->api_key;
                $_GET['apitimestamp'] = time();
                $signature = Generator::genSignature($apikey->api_secret_key);
                $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = $signature;
            }
        } elseif ($theClass === 'IntermediatePubAuthController') {
            $namespace = 'Orbit\Controller\API\v1\Pub\\';

            // Current user should be available in the container,
            // no need to fetch it from DB again.
            $user = App::make('currentUser');

            // Fetch apikey, or create a new one if doesn't exists.
            $apikey = $user->apikey;

            if (empty($apikey)) {
                // Create new one
                $apikey = $user->createAPiKey();
            }

            // Generate the signature
            $_GET['apikey'] = $apikey->api_key;
            $_GET['apitimestamp'] = time();
            $signature = Generator::genSignature($apikey->api_secret_key);
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = $signature;

        } elseif ($theClass === 'IntermediateMerchantAuthController') {
            $namespace = 'Orbit\Controller\API\v1\Merchant\\';
            if ($userId = $this->authCheck()) {
                $user = User::findOnWriteConnection($userId);

                // This will query the database if the apikey has not been set up yet
                $apikey = $user->apikey;

                if (empty($apikey)) {
                    // Create new one
                    $apikey = $user->createAPiKey();
                }

                // Generate the signature
                $_GET['apikey'] = $apikey->api_key;
                $_GET['apitimestamp'] = time();
                $signature = Generator::genSignature($apikey->api_secret_key);
                $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = $signature;
            }
        } elseif ($theClass === 'IntermediateMerchantTransactionAuthController') {
            $namespace = 'Orbit\Controller\API\v1\MerchantTransaction\\';
            if ($userId = $this->authCheck()) {
                $user = User::findOnWriteConnection($userId);

                // This will query the database if the apikey has not been set up yet
                $apikey = $user->apikey;

                if (empty($apikey)) {
                    // Create new one
                    $apikey = $user->createAPiKey();
                }

                // Generate the signature
                $_GET['apikey'] = $apikey->api_key;
                $_GET['apitimestamp'] = time();
                $signature = Generator::genSignature($apikey->api_secret_key);
                $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = $signature;
            }
        } elseif ($theClass === 'IntermediateArticleAuthController') {
            $namespace = 'Orbit\Controller\API\v1\Article\\';
            if ($userId = $this->authCheck()) {
                $user = User::findOnWriteConnection($userId);

                // This will query the database if the apikey has not been set up yet
                $apikey = $user->apikey;

                if (empty($apikey)) {
                    // Create new one
                    $apikey = $user->createAPiKey();
                }

                // Generate the signature
                $_GET['apikey'] = $apikey->api_key;
                $_GET['apitimestamp'] = time();
                $signature = Generator::genSignature($apikey->api_secret_key);
                $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = $signature;
            }
        } elseif ($theClass === 'IntermediateProductAuthController') {
            $namespace = 'Orbit\Controller\API\v1\Product\\';

            $user = App::make('currentUser');

            // This will query the database if the apikey has not been set up yet
            $apikey = $user->apikey;

            if (empty($apikey)) {
                // Create new one
                $apikey = $user->createAPiKey();
            }

            // Generate the signature
            $_GET['apikey'] = $apikey->api_key;
            $_GET['apitimestamp'] = time();
            $signature = Generator::genSignature($apikey->api_secret_key);
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = $signature;
        } elseif ($theClass === 'IntermediateBrandProductAuthController') {
            $namespace = 'Orbit\Controller\API\v1\BrandProduct\\';

            $user = App::make('currentUser');

            // This will query the database if the apikey has not been set up yet
            $apikey = $user->apikey;

            if (empty($apikey)) {
                // Create new one
                $apikey = $user->createAPiKey();
            }

            // Generate the signature
            $_GET['apikey'] = $apikey->api_key;
            $_GET['apitimestamp'] = time();
            $signature = Generator::genSignature($apikey->api_secret_key);
            $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = $signature;
        }

        // Call the API class
        // Using either callApi('controller', 'method') or callApi('controller@method')
        $args = func_get_args();

        if (count($args) === 2) {
            $class = $namespace . $args[0];
            $method = $args[1];
        } elseif (count($args) === 1) {
            list($class, $method) = explode('@', $args[0]);
            $class = $namespace . $class;
        } else {
            $class = 'Foo';
            $method = 'Bar';
        }

        $supportedDependenciesInjection = [
            'IntermediatePubAuthController',
            'IntermediateProductAuthController',
            'IntermediateBrandProductAuthController',
        ];

        if (in_array($theClass, $supportedDependenciesInjection)) {
            // Handle the request with Reflection.
            return $this->handleRequest($class, $method, $user);
        }

        return $class::create()->$method();
    }

    /**
     * Get the name of API Controller which we want to call.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    protected function getTargetAPIController($method)
    {
        // Remove the 'Intermediate' string
        list($controller, $method) = explode('_', $method);

        // Append the controller with 'APIController', e.g:
        // 'Merchant' would be 'MerchantAPIController'
        $controller .= 'APIController';

        return array(
            'controller' => $controller,
            'method'     => $method
        );
    }

    /**
     * Magic method to call the API based on called method.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $method Method name
     * @param array $args Arguments
     * @return mixed
     */
    public function __call($method, $args)
    {
        $api = $this->getTargetAPIController($method);

        return $this->callApi($api['controller'], $api['method']);
    }

    /**
     * Check the authentication status.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return int - User ID
     */
    protected function authCheck()
    {
        $userId = $this->session->read('user_id');
        if ($this->session->read('logged_in') !== TRUE || ! $userId) {
            return FALSE;
        }

        return $userId;
    }

    /**
     * Check the authentication status from angular CI.
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @return int - User ID
     */
    protected function authCheckFromAngularCI()
    {
        $userId = $this->session->read('user_id');

        if (empty($userId)) {
            $userId = $this->session->read('guest_user_id');
            if (empty($userId)) {
                return FALSE;
            }
        }

        return $userId;
    }

    /**
     * Handle the request.
     *
     * First, we would resolve dependencies defined in the controller method
     * which assigned to handle the request. Then, call the actual handler/method
     * with the resolved dependencies as parameters.
     *
     * @param  string|Controller $class the controller
     * @param  string $method the method that handles the request
     * @param  User $user   current User instance (logged in or guest)
     *
     * @return Illuminate\Http\Response
     */
    protected function handleRequest($class, $method, $user)
    {
        // Resolve the dependencies...
        $dependencies = $this->resolveDependencies($class, $method);

        // Instantiate the class and set the user, just like old behaviour.
        // We don't have to worry about old behaviour since it will not be affected
        // in any way.
        $controller = $class::create();

        if (method_exists($controller, 'setUser')) {
            $controller->setUser($user);
        }

        // Then, call actual handler/method on the controller and pass the
        // resolved dependencies as its parameters.
        return call_user_func_array([$controller, $method], $dependencies);
    }

    /**
     * Resolve any dependencies defined in the method parameters.
     *
     * @todo  support primitive types?
     *
     * @param  string|Controller $class  controller name
     * @param  string $method the method that handles the request
     *
     * @return array $dependecies list of resolved dependencies
     */
    protected function resolveDependencies($class, $method)
    {
        // Get the method details...
        $reflectionMethod = new ReflectionMethod($class, $method);

        // Get its parameter definition...
        $reflectionParams = $reflectionMethod->getParameters();

        // For each parameter, try to resolve it from container.
        $dependencies = [];
        foreach($reflectionParams as $reflectionParam) {

            // Get parameter details.
            $param = new ReflectionParameter(
                [$class, $method], $reflectionParam->name
            );

            // Get the class/type of the parameter.
            $dependencyClass = $param->getClass();

            // Then, resolve it from container.
            $dependencies[] = App::make($dependencyClass->name);
        }

        return $dependencies;
    }

    /**
     * Handle exception, mostly for handling authorization exception.
     *
     * At this moment, will be called by child classes, e.g.
     * IntermediatePubAuthController->beforeFilter() hooks
     *
     * @see  Orbit\Controller\API\v1\ErrorServiceProvider
     * @param  Exception $e the exception.
     * @throws Exception $e the exception.
     */
    protected function handleException($e)
    {
        // Re-throw to global exception handling.
        throw $e;
    }
}
