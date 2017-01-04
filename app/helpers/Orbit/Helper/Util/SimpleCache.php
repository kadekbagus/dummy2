<?php namespace Orbit\Helper\Util;
/**
 * Simple helper to provide simplifier method to cache result set.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Cache;

class SimpleCache
{
    /**
     * Key for the cache
     *
     * @var string
     */
    protected $key = '';

    /**
     * Prefix of the key
     *
     * @var string
     */
    protected $keyPrefix = '';

    /**
     * Current page. Could be 'default' or one of the key config.
     *
     * @string
     */
    protected $context = 'default';

    /**
     * The main config
     *
     * @var array
     */
    protected $config = [
        'default' => [
            // Enable or disable the cache
            'enable' => FALSE,

            // Cache the result for X minutes
            'cache_ttl' => 15,
        ],

        /*
        // promotion list specific cache setting
        'promotion-list' => [
            'enable' => FALSE,
            'cache_ttl' => 30
        ],

        // coupon list specific cache setting
        'coupon-list' => [
            'enable' => FALSE,
            'cache_ttl' => 30
        ],

        // store list specific cache setting
        'store-list' => [
            'enable' => FALSE,
            'cache_ttl' => 30
        ],

        // mall list specific cache setting
        'mall-list' => [
            'enable' => FALSE,
            'cache_ttl' => 30
        ],

        // event list specific cache setting
        'event-list' => [
            'enable' => FALSE,
            'cache_ttl' => 30
        ],
        */
    ];

    /**
     * @param array $config
     * @return void
     */
    public function __construct(array $config=[], $context='default')
    {
        $this->config = $config + $this->config;
        $this->context = $context;
        // Default key prefix set the same as context
        $this->keyPrefix = $context;
    }

    /**
     * @param array $config
     * @return Cache
     */
    public static function create(array $config=[], $context='default')
    {
        return new Static($config, $context);
    }

    /**
     * Set the config
     *
     * @param array $config
     * @return Cache
     */
    public function setConfig(array $config=[])
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get the config
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Set

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param string $context
     * @return $this
     */
    public function setContext($context)
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @return $string
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param string $prefix
     * @return SimpleCache
     */
    public function setKeyPrefix($prefix)
    {
        $this->keyPrefix = $prefix;

        return $this;
    }

    /**
     * @return string
     */
    public function getKeyPrefix()
    {
        return $this->keyPrefix;
    }

    /**
     * Put the data to cache
     *
     * @param string $key
     * @param mixed $data
     * @return $this
     */
    public function put($key, $data)
    {
        if ($this->getConfigValue('enable') === FALSE) {
            return $this;
        }

        // Reformat key to add prefix of the context
        $key = $this->keyPrefix . ':' . $key;

        // Time to live
        $ttl = $this->getConfigValue('cache_ttl');

        // Put the data into the cache and tag them also
        Cache::put($key, $data, $ttl);

        return $this;
    }

    /**
     * Get the cache based on key
     *
     * @param string $key
     * @param callback $callback executed when no cache found
     * @return boolean|mixed
     */
    public function get($key, callable $callback=NULL)
    {
        // Does the cache enabled?
        if ($this->getConfigValue('enable') === FALSE) {
            return (is_callable($callback) ? $callback() : FALSE);
        }

        $key = $this->keyPrefix . ':' . $key;

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        // The cache is enabled but there is no cache found
        return (is_callable($callback) ? $callback() : FALSE);
    }

    /**
     * Transform an arrays key to hash
     *
     * @param array $array
     * @param int $keyLength
     * @return string
     */
    public static function transformArrayToHash($array, $keyLength=12)
    {
        return substr(sha1( serialize($array) ), 0, $keyLength);
    }

    /**
     * Get config key and fallback to default if it is not exists
     *
     * @param string $name 'enable', 'cache_ttl'
     * @return boolean
     */
    protected function getConfigValue($name)
    {
        // Use global if specific cache is not exists
        if (! array_key_exists($this->context, $this->config)) {
            return $this->config['default'][$name];
        }

        // Specific page config exists use it instead
        if (! array_key_exists($name, $this->config[$this->context])) {
            return $this->config['default'][$name];
        }

        return $this->config[$this->context][$name];
    }
}