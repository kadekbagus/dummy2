<?php namespace Orbit\Helper\Database;
/**
 * Simple helper to provide shortcut for caching database
 * result based on config.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class Cache
{
    /**
     * The Eloquent or query builder object.
     *
     * @var Eloquent|QueryBuilder
     */
    protected $model;

    /**
     * The main config
     *
     * @var array
     */
    protected $config = [
        // Enable or disable the cache
        'enable' => FALSE,

        // Cache the result for X minutes
        'cache_ttl' => 15,

        // Not used at the moment
        // driver = 'file',
    ];

    /**
     * @param array $config
     * @return void
     */
    public function __construct(array $config=[])
    {
        $this->config = $config + $this->config;
    }

    /**
     * @param array $config
     * @return Cache
     */
    public static function create(array $config=[])
    {
        return new Static($config);
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
     * Main method to cache
     *
     * @param Model|QueryBuilder
     * @return Model
     */
    public function remember($model)
    {
        $this->model = $model;

        if (! $this->config['enable']) {
            return $this->model;
        }

        $this->model->remember($this->config['cache_ttl']);

        return $this->model;
    }
}