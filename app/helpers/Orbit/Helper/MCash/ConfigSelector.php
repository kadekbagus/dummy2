<?php namespace Orbit\Helper\MCash;

/**
 * Switch config between production or sandbox
 */
class ConfigSelector
{
    protected $config = [];

    public function __construct($config)
    {
        $this->config = $config;
    }

    public static function create($config)
    {
        return new static($config);
    }

    public function getConfig()
    {
        switch ($this->config['is_production']) {
            case true:
                $this->config['base_uri'] = $this->config['base_uri']['production'];
                $this->config['auth']['username'] = $this->config['auth']['production']['username'];
                $this->config['auth']['key'] = $this->config['auth']['production']['key'];
                break;

            case false:
                $this->config['base_uri'] = $this->config['base_uri']['sandbox'];
                $this->config['auth']['username'] = $this->config['auth']['sandbox']['username'];
                $this->config['auth']['key'] = $this->config['auth']['sandbox']['key'];
                break;
        }
        unset($this->config['auth']['production']);
        unset($this->config['auth']['sandbox']);

        return $this->config;
    }
}