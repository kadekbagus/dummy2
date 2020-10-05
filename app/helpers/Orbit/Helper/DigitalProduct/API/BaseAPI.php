<?php namespace Orbit\Helper\DigitalProduct\API;

use Config;
use Exception;
use GuzzleHttp\Client as Guzzle;
use Orbit\Helper\DigitalProduct\Response\BaseResponse;

/**
 * Base 3rd party API wrapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BaseAPI
{
    /**
     * Guzzle client.
     * @var null
     */
    protected $client = null;

    /**
     * Guzzle config.
     * @var array
     */
    protected $config = [];

    /**
     * API Endpoint
     * @var string
     */
    protected $endPoint = '';

    /**
     * Guzzle options.
     * @var array
     */
    protected $options = [];

    /**
     * API query string?
     * @var array
     */
    protected $queryString = [];

    /**
     * Provider ID so we can read config file properly.
     * @var string
     */
    protected $providerId = '';

    /**
     * Request contentType.
     * @var string
     */
    protected $contentType = 'application/json';


    protected $randomizeResponseChance = [1, 1, 1, 1, 0, 0];

    /**
     * Indicate that we should mock the response.
     * For local usage only/when we can not access 3rd party API.
     * @var boolean
     */
    protected $shouldMockResponse = false;

    /**
     * Mock response data.
     * @var null
     */
    protected $mockResponse = null;

    /**
     * Request data that required for building request param to 3rd party API.
     * @var array
     */
    protected $requestData = [];

    /**
     * Construct the base api client.
     *
     * @param array $customConfig [description]
     */
    public function __construct($requestData = [], $customConfig = [])
    {
        $this->config = $this->getConfig($customConfig);

        $this->client = new Guzzle([
            'base_uri' => $this->config['base_uri']
        ]);

        $this->requestData = $requestData;
    }

    public static function create($requestData = [], $customConfig = [])
    {
        return new static($requestData, $customConfig);
    }

    public function setQueryStrings($queryString = [])
    {
        $this->queryString = array_merge($this->queryString, $queryString);

        return $this;
    }

    /**
     * Get current provider env.
     *
     * @return string
     */
    protected function getEnv()
    {
        return Config::get('orbit.digital_product.providers.env', 'local');
    }

    /**
     * Get provider config based on current env.
     *
     * @return array
     */
    protected function getConfig($customConfig = [])
    {
        $orbitConfig = Config::get("orbit.digital_product.providers.{$this->providerId}.config.{$this->getEnv()}", []);

        return array_merge($orbitConfig, $customConfig);
    }

    /**
     * Build request body/param.
     *
     * @return [type] [description]
     */
    protected function buildRequestParam()
    {
        return null;
    }

    /**
     * Mock response data.
     *
     * @return [type] [description]
     */
    protected function mockResponseData()
    {
        $this->mockResponseData = null;
    }

    /**
     * Format response.
     *
     * @param  [type] $response [description]
     * @return [type]           [description]
     */
    protected function response($response)
    {
        return new BaseResponse($response);
    }

    /**
     * Get request data.
     *
     * @return [type] [description]
     */
    public function getRequestData()
    {
        return $this->requestData;
    }

    /**
     * Do the actual request to 3rd party API.
     *
     * @return [type] [description]
     */
    protected function request()
    {
        try {
            $this->addQueryString();

            $this->setRequestContentType();

            $this->setHeaders();

            // Do the request...
            if (! $this->shouldMockResponse) {

                $response = $this->client->request(
                    $this->method,
                    $this->endPoint,
                    $this->options
                );

                $response = $response->getBody()->getContents();
            }
            else {
                $response = $this->mockResponse;
            }

        } catch(Exception $e) {
            return $this->handleException($e);
        }

        return $this->response($response);
    }

    /**
     * Add query string to the request url.
     *
     * Can be overridden as needed.
     */
    protected function addQueryString()
    {
        // Add query string if needed.
        if (! empty($this->queryString)) {
            $this->options['query'] = $this->queryString;
        }
    }

    /**
     * Set Content-Type request header.
     *
     * Can be overridden as needed.
     */
    protected function setRequestContentType()
    {
        if (! empty($this->contentType)) {
            // Set header...
            $this->options['headers']['Content-Type'] = $this->contentType;
        }
    }

    /**
     * Set additional request headers.
     */
    protected function setHeaders()
    {
        //
    }

    /**
     * Handle exception while running the request.
     *
     * @param  [type] $e [description]
     * @return [type]    [description]
     */
    protected function handleException($e)
    {
        $response = (object) [
            'status' => $e->getCode(),
            'message' => $e->getMessage(),
            'data' => null,
        ];

        return $this->response($response);
    }

    /**
     * Set request body params.
     *
     * Can be overridden as needed.
     */
    protected function setBodyParams()
    {
        $this->options['body'] = $this->buildRequestParam();
    }

    /**
     * Run the api.
     *
     * @param  array  $requestData [description]
     * @return [type]              [description]
     */
    public function run($requestData = [])
    {
        $this->requestData = array_merge($this->requestData, $requestData);

        $this->setBodyParams();

        if ($this->shouldMockResponse) {
            $this->mockResponseData();
        }

        return $this->request();
    }
}
