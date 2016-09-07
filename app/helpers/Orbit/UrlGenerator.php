<?php
namespace Orbit;


use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use Config;
use Orbit\Helper\Session\AppOriginProcessor;

/**
 * Extends url generator to insert session id in url.
 *
 * @package Orbit
 */
class UrlGenerator extends \Illuminate\Routing\UrlGenerator
{
    protected function getAppOrigin()
    {
        return $appOrigin = AppOriginProcessor::create(Config::get('orbit.session.app_list'))
                                ->getAppName();
    }

    /**
     * @return string
     */
    protected function getSessionIdParameterName()
    {
        $paramName = Config::get('orbit.session.session_origin.query_string.name', '');
        if (! empty($this->getAppOrigin())) {
            $paramName = Config::get('orbit.session.origin.' . $this->getAppOrigin() . '.query_string.name', '');
        }
        return $paramName;
    }

    /**
     * Get utm parameter as query string or array
     * @author Ahmad <ahmad@dominopos.com>
     * @param string $selector ('string' or 'array')
     * @return string|array
     */
    protected function getUtmParameter($selector = 'string')
    {
        $queryString = '';
        $queryStringArray = [];
        if (Config::get('orbit.shop.append_utm_tracking_in_url', FALSE)) {
            $utmParams = Config::get('orbit.shop.utm_parameters', []);
            foreach ($utmParams as $utmParam) {
                if (array_key_exists($utmParam, $_GET)) {
                    $queryStringArray[$utmParam] = $_GET[$utmParam];
                }
            }
        }

        if ($selector === 'array') {
            return $queryStringArray;
        }

        if (! empty($queryStringArray)) {
            $queryString = http_build_query($queryStringArray);
        }

        return $queryString;
    }

    /**
     * @return null|string
     * @throws \Exception
     */
    protected function getSessionIdValue()
    {
        // Return mall_portal, cs_portal, pmp_portal etc
        $appOrigin = $this->getAppOrigin();

        // Session Config
        $orbitSessionConfig = Config::get('orbit.session.origin.' . $appOrigin);
        $applicationId = Config::get('orbit.session.app_id.' . $appOrigin);

        // Instantiate the OrbitSession object
        $config = new SessionConfig(Config::get('orbit.session'));
        $config->setConfig('session_origin', $orbitSessionConfig);
        $config->setConfig('expire', $orbitSessionConfig['expire']);
        $config->setConfig('application_id', $applicationId);

        $session = new Session($config);
        try {
            $session->start([], 'no-session-creation');
            return $session->getSessionId();
        } catch (\Exception $e) {
            $code = $e->getCode();
            if (in_array($code, [Session::ERR_SESS_NOT_FOUND, Session::ERR_IP_MISS_MATCH, Session::ERR_UA_MISS_MATCH, Session::ERR_SESS_EXPIRE], true)) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * @return bool
     */
    protected function getSessionInUrlEnabled()
    {
        $enabled = Config::get('orbit.session.availability.query_string', false);
        $name_set = Config::get('orbit.session.session_origin.query_string.name', null) !== null;

        return ($enabled && $name_set);
    }

    protected function getRouteQueryString(array $parameters)
    {
        if ($this->getSessionInUrlEnabled()) {
            $id = $this->getSessionIdValue();
            if ($id !== null) {
                $parameters[$this->getSessionIdParameterName()] = $id;
            }
        }
        return parent::getRouteQueryString($parameters);
    }

    public function to($path, $extra = array(), $secure = null)
    {
        $original = parent::to($path, $extra, $secure);

        $sessionString = '';
        if ($this->getSessionInUrlEnabled()) {
            $id = $this->getSessionIdValue();
            if ($id !== null) {
                // session string
                $sessionString = http_build_query([$this->getSessionIdParameterName() => $id]);
            }
        }
        // utm parameters
        $utmString = $this->getUtmParameter();

        // array of query strings that will be appended
        $appendedQueryStrings = array(
            $utmString,
            $sessionString
        );

        // glue the appended string excluding empty values('', 0, NULL, FALSE)
        $additional_query = implode('&', array_filter($appendedQueryStrings));

        $insert_qmark = false;
        $insert_ampersand = false;
        $insert_at = -1;

        $frag = strpos($original, '#');
        $qmark = strpos($original, '?');
        if ($frag !== false) {
            if ($qmark !== false) {
                if ($qmark > $frag) {
                    // ....#...?...
                    $insert_at = $frag;
                    $insert_qmark = true;
                } else {
                    // ....?...#...
                    $insert_at = $qmark + 1;
                    $insert_ampersand = true;
                }
            } else {
                // ....#...
                $insert_at = $frag;
                $insert_qmark = true;
            }
        } else {
            if ($qmark !== false) {
                // ....?...
                $insert_at = $qmark + 1;
                $insert_ampersand = true;
            } else {
                // ....
                $insert_at = strlen($original);
                $insert_qmark = empty($additional_query) ? false : true;
            }
        }

        if ($insert_at !== -1) {
            $pre = substr($original, 0, $insert_at);
            $post = substr($original, $insert_at);
            $result = $pre . ($insert_qmark ? '?' : '') . $additional_query . ($insert_ampersand ? '&' : '') . $post;
        } else {
            $result = $original;
        }

        return $result;
    }

    /**
     * Get the URL to a named route and add extra query parameter.
     *
     * @param  string  $name
     * @param  mixed   $parameters
     * @param  bool  $absolute
     * @param  \Illuminate\Routing\Route  $route
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function route($name, $parameters = array(), $absolute = true, $route = null)
    {
        $route = $route ?: $this->routes->getByName($name);

        // append extra parameters (utm parameters) to current parameters
        $utmParams = $this->getUtmParameter('array');
        $parameters = array_merge($parameters, $utmParams);

        $parameters = (array) $parameters;

        if ( ! is_null($route))
        {
            return parent::toRoute($route, $parameters, $absolute);
        }
        else
        {
            throw new InvalidArgumentException("Route [{$name}] not defined.");
        }
    }

    /**
     * Called by hiddenSessionIdField below.
     *
     * Not called directly as URL::instanceHiddenSessionIdField to prevent crashing if
     * using standard Laravel UrlGenerator.
     *
     * @return string
     */
    public function instanceHiddenSessionIdField()
    {
        if (!$this->getSessionInUrlEnabled()) {
            return '';
        }
        $id = $this->getSessionIdValue();
        if ($id === null) {
            return '';
        }
        return \Form::hidden($this->getSessionIdParameterName(), $id);
    }

    /**
     * Returns HTML for a hidden field containing session id (for use in GET forms), or empty string.
     *
     * Empty string if: URL Generator not configured, session-in-url not configured.
     *
     * @return string
     */
    public static function hiddenSessionIdField()
    {
        $gen = \URL::getFacadeRoot();
        if (is_callable([$gen, 'instanceHiddenSessionIdField'])) {
            return $gen->instanceHiddenSessionIdField();
        }
        return '';
    }

    /**
     * Determine asset URL based on "orbit.assets.root" config, or defers to parent if not set.
     *
     * @param string $path path
     * @param bool|null $secure secure or not (or use existing protocol)
     * @return string
     */
    public function asset($path, $secure = null)
    {
        if ($this->isValidUrl($path)) return $path;

        $assetRoot = Config::get('orbit.assets.root', false);
        if (!$assetRoot) {
            // not defined, use parent
            return parent::asset($path, $secure);
        }

        $scheme = $this->getScheme($secure);
        $start = starts_with($assetRoot, 'http://') ? 'http://' : 'https://';
        $root = preg_replace('~'.$start.'~', $scheme, $assetRoot, 1);

        return $this->removeIndex($root).'/'.trim($path, '/');
    }

}
