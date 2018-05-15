<?php namespace Orbit\Helper\Sepulsa\API;

use Orbit\Helper\Sepulsa\Client as SepulsaClient;
use Orbit\Helper\Sepulsa\ConfigSelector;
use Config;
use Cache;

/**
 * Get Campaign List from Sepulsa
 */
class Login
{
    /**
     * HTTP Client
     */
    protected $client;

    /**
     * Configs
     */
    protected $config;

    /**
     * Campaign list endpoint
     */
    protected $endpoint = 'auth/login';

    /**
     * Login response
     */
    protected $loginResponse;

    public function __construct($config=[])
    {
        $this->config = ! empty($config) ? $config : Config::get('orbit.partners_api.sepulsa');
        $this->client = SepulsaClient::create($this->config);
    }

    public static function create($config=[])
    {
        return new static($config);
    }

    public function login()
    {
        try {
            $this->config = ConfigSelector::create($this->config)->getConfig();

            $requestParams = [
                "email" => $this->config['auth']['username'],
                "password" => $this->config['auth']['password']
            ];

            $requestHeaders = [
                'Content-Type' => 'application/json',
            ];

            $response = $this->client
                ->setHeaders($requestHeaders)
                ->setJsonBody($requestParams)
                ->setEndpoint($this->endpoint)
                ->request('POST');

            $this->response = $response;

            return $this;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function saveToken()
    {
        if (isset($this->response)) {
            Cache::put($this->config['session_key_name'], 'Bearer ' . $this->response->result->token, 60);
        }
    }
}