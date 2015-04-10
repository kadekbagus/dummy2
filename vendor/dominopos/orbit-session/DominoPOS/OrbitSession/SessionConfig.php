<?php namespace DominoPOS\OrbitSession;
/**
 * Library for storing session configuration
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class SessionConfig
{
    /**
     * List of configuration passed by the user.
     *
     * @var array
     */
    protected $config = array();

    /**
     * List of default configuration.
     *
     * @var array
     */
    protected $default = array();

    /**
     * Store the cached config
     *
     * @var array
     */
    protected $cachedConfig = array();

    /**
     * Class constructor for setting the default configuration
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $config
     */
    public function __construct(array $config=array())
    {
        $this->default = array(
            /**
             * How long session will expire in seconds
             */
            'expire' => 3600,

            /**
             * Path to write the session data
             */
            'path' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'orbit-session',

            /**
             * Strict mode, will check the user agent and ip address
             */
            'strict' => TRUE,

            /**
             * Session Driver
             */
            'driver' => 'file',

            /**
             * Session data available
             */
            'availability' => array(
                'header'        => TRUE,
                'query_string'  => TRUE,
                'cookie'        => TRUE,
            ),

            /**
             * Where is session data should be coming from
             */
            'session_origin' => array(
                // From HTTP Headers
                'header'  => array(
                    'name'      => 'X-Orbit-Session'
                ),

                // From Query String
                'query_string' => array(
                    'name'      => 'orbit_session'
                ),

                // From Cookie
                'cookie'    => array(
                    'name'      => 'orbit_sessionx',

                    // Expire time, should be set equals or higher than
                    // SessionConifg.expire
                    'expire' => 62208000,   // two years

                    // Path of the cookie
                    'path'      => '/',

                    // domain
                    'domain'    => NULL,

                    // secure transfer via HTTPS only
                    'secure'    => FALSE,

                    // Deny access from client side script
                    'httponly'  => TRUE
                ),
            ),
        );

        $this->config = $config + $this->default;
    }

    /**
     * Get uploader config using dotted configuration.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $key Config name
     * @param mixed $defualt Default value
     * @return mixed
     */
    public function getConfig($key, $default=NULL)
    {
        // Check inside cache first
        if (array_key_exists($key, $this->cachedConfig)) {
            return $this->cachedConfig[$key];
        }

        $config = static::getConfigVal($key, $this->config, $default);
        $this->cachedConfig[$key] = $config;

        return $config;
    }

    /**
     * Set uploader config using dotted configuration
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $key Config name
     * @param mixed $value Value of the config
     * @return UploaderConfig
     */
    public function setConfig($key, $value)
    {
        static::setConfigVal($key, $value, $this->config);

        // Update the cache
        $this->cachedConfig[$key] = $value;

        return $this;
    }

    /**
     * Clear the config cache.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return UploaderConfig
     */
    public function clearConfigCache()
    {
        $this->cachedConfig = array();

        return $this;
    }

    /**
     * Static method for getting configuration from an array using the "." dot
     * notation.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @credit Laravel Illuminate\Support\Arr
     *
     * @param string $key The dotted configuration name
     * @param array $key Source of the config
     * @param mixed $default Default value given when no matching config found
     * @return mixed
     */
    public static function getConfigVal($key, $array, $default=NULL)
    {
        // Return all config
        if (is_null($key)) {
            return $array;
        }

        // No need further check if we found the element at first
        if (isset($array[$key])) {
            return $array[$key];
        }

        // Split the key by dot notation and loop through the array
        foreach (explode('.', $key) as $keyname) {
            //     Does this $key exists?
            if (! array_key_exists($keyname, $array)) {
                return $default;
            }

            // Set the value of $copy to the next array value
            // Left to Right till the last key
            $array = $array[$keyname];
        }

        return $array;
    }

    /**
     * Static method for setting configuration using "." dot notation.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @credit Laravel Illuminate\Support\Arr
     *
     * @param string $key The dotted name of the configuration
     * @param mixed $value Value of the new config
     * @param array &$array Array target of the new configuration
     * @return array
     */
    public static function setConfigVal($key, $value, &$array)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        // Explode the key by dot notation
        $keys = explode('.', $key);

        // Loop through the key except for the last one
        while (count($keys) > 1) {
            // shift the first element of the keys
            $key = array_shift($keys);

            // If one of the key does not exists on the original array
            // then we need create it so we can reach the last array
            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = array();
            }

            // Rebuild the array till before the last one
            $array =& $array[$key];
        }

        // Get the last key and assign it to the array
        $array[array_shift($keys)] = $value;

        return $array;
    }
}
