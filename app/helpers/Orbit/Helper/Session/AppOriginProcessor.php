<?php namespace Orbit\Helper\Session;
/**
 * Get the application origin which should came from either 1. query string or
 * 2. http header
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class AppOriginProcessor
{
    /**
     * List of mapping between application and session name. Key pair
     * of the app and the orbit session name that should used by
     * each app.
     *
     * By using different session name the session will not conflict
     * in one browser when user using open more than one tab and
     * different application at the same time.
     *
     * @var array
     */
    protected $appList = [];

    /**
     * Argument to determine the application name.
     *
     * @var string
     */
    protected $originConfigName = 'X-Orbit-App-Origin';

    /**
     * Values of query string.
     *
     * @var array
     */
    protected $queryStrings = [];

    /**
     * Values of HTTP headers.
     *
     * @var array
     */
    protected $httpHeaders = [];

    /**
     * Set default name for app.
     *
     * @var string
     */
    protected $defaultAppName = 'mobile_ci';

    /**
     * Constructor
     *
     * @param array $appList
     * @return void
     */
    public function __construct($appList=[])
    {

        $this->appList = $appList + [
            'mobile_ci' => 'orbit_session',
            'desktop_ci' => 'orbit_session',
            'landing_page' => 'orbit_session',

            // Non Customer
            'mall_portal' => 'orbit_session',
            'cs_portal' => 'orbit_session',
            'pmp_portal' => 'orbit_session',
            'admin_portal' => 'orbit_session'
        ];

        $this->queryStrings = $_GET;
        $this->httpHeaders = $_SERVER;
    }

    /**
     * Static creation of the object.
     *
     * @param array $appList
     * @return AppOriginGetter
     */
    public static function create($appList=[])
    {
        return new static($appList);
    }

    /**
     * @param array $list
     * @return AppOriginGetter
     */
    public function setAppList(array $list)
    {
        $this->appList = $list;

        return $this;
    }

    /**
     * @param string $name
     * @return AppOriginGetter
     */
    public function setOriginConfigName($name)
    {
        $this->originConfigName = $name;

        return $this;
    }

    /**
     * @param array $get
     * @return AppOriginGetter
     */
    public function setQueryStrings(array $get)
    {
        $this->queryStrings = $get;

        return $this;
    }

    /**
     * @param array $headers
     * @return AppOriginGetter
     */
    public function setHttpHeaders(array $headers)
    {
        $this->httpHeaders = $headers;

        return $this;
    }

    /**
     * @param string $name
     * @return AppOriginGetter
     */
    public function setDefaultAppName($name)
    {
        $this->defaultAppName = $name;

        return $this;
    }

    /**
     * Get the application name.
     *
     * @param string $target 'app_name' or 'session_name'
     * @return string
     */
    public function getAppName($target='app_name')
    {
        $appName = $this->searchInQueryString();

        if (is_null($appName)) {
            $appName = $this->searchInHttpHeaders();
        }

        if (empty($appName)) {
            if (array_key_exists($this->defaultAppName, $this->appList)) {
                if ($target === 'app_name') {
                    return $this->defaultAppName;
                }

                if ($target === 'session_name') {
                    return $this->appList[$this->defaultAppName];
                }
            }

            return NULL;
        }

        if (array_key_exists($appName, $this->appList)) {
            if ($target === 'app_name') {
                return $appName;
            }

            if ($target === 'session_name') {
                return $this->appList[$appName];
            }
        }

        return NULL;
    }

    /**
     * Get the session name.
     *
     * @return string
     */
    public function getSessionName()
    {
        return $this->getAppName('session_name');
    }

    /**
     * Search the argument name inside the query string
     *
     * @return array|null
     */
    protected function searchInQueryString()
    {
        if (array_key_exists($this->originConfigName, $this->queryStrings)) {
            return $this->queryStrings[$this->originConfigName];
        }

        return NULL;
    }

    /**
     * Search the argument name inside the http headers
     *
     * @return array|null
     */
    protected function searchInHttpHeaders()
    {
        $headerName = str_replace('-', '_', $this->originConfigName);
        $headerName = 'HTTP_' . strtoupper($headerName);

        if (array_key_exists($this->originConfigName, $this->httpHeaders)) {
            return $this->httpHeaders[$this->originConfigName];
        }

        return NULL;
    }

    /**
     * Magic call to get the protected property.
     *
     * @return mixed
     */
    public function __get($property)
    {
        if (property_exists($this, $property)) {
            return $this->${property};
        }

        return NULL;
    }
}