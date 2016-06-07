<?php namespace Orbit\Helper\Util;
/**
 * Helper to print the Allow-Origin for CORS request. So the javascript
 * client can read the header.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class CorsHeader
{
    /**
     * Config for CORS
     *
     * @var array
     */
    protected $config = array();

    public function __construct($optionalConfig=[])
    {
        $this->config['allow-methods'] = 'GET, POST';
        $this->config['allow-origin'] = ['example.com'];
        $this->config['allow-credentials'] = 'true';
        $this->config['allow-headers'] = [
            'Origin',
            'Content-Type',
            'Accept',
            'Authorization',
            'X-Request-With',
            'X-Orbit-Signature',
            'Cookie',
            'Set-Cookie'
        ];

        $this->config = $optionalConfig + $this->config;
    }

    /**
     * Static method to instantiate the class
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return IntermediateBaseController
     */
    public static function create($optionalConfig=[])
    {
        return new static($optionalConfig);
    }

    public function getAllowOrigin($currentOrigin=NULL)
    {
        if (! $currentOrigin) {
            $currentOrigin = $_SERVER['HTTP_ORIGIN'];
        }

        if (! is_array($this->config['allow-origin']) && $this->config['allow-origin'] === '*') {
            return '*';
        }

        // Compare current origin with list of allowed origin
        foreach ($this->config['allow-origin'] as $allowed) {
            if (preg_match('/' . $allowed . '$/i', $currentOrigin)) {
                return $currentOrigin;
            }
        }

        return 'localhost';
    }

    public function getAllowMethods() {
        return $this->config['allow-methods'];
    }

    public function getAllowCredentials() {
        return $this->config['allow-credentials'];
    }

    public function getAllowHeaders() {
        return $this->config['allow-headers'];
    }
}