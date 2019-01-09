<?php
/**
 * Base controller class for Intermediate Controller
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\Helper\Generator;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitSession\Session as OrbitSession;
use DominoPOS\OrbitSession\SessionConfig;
use Orbit\Helper\Util\CorsHeader;
use Orbit\Helper\Session\AppOriginProcessor;

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
            if ($userId = $this->authCheckPub()) {
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

        if ($theClass === 'IntermediatePubAuthController') {
            // set PubControllerAPI user property, so user will be available inside controllers
            return $class::create()->setUser($user)->$method();
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
     * Check the authentication status from Pub Controllers.
     *
     * @author Ahmad <ahmad@dominopos.com>
     * @return string - User ID
     */
    protected function authCheckPub()
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
}
