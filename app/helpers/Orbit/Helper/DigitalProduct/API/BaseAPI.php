<?php namespace Orbit\Helper\DigitalProduct\API;

use Config;
use Exception;
use GuzzleHttp\Client as Guzzle;
use Log;
use Orbit\Helper\Exception\OrbitCustomException;

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
        $env = Config::get('orbit.digital_product.providers.env', 'local');
        $orbitConfig = Config::get("orbit.digital_product.providers.{$this->providerId}.config.{$env}", []);

        $this->config = array_merge($orbitConfig, $customConfig);

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
        return new BasicResponse($response);
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
    public function request()
    {
        try {
            // Add query string if needed.
            if (! empty($this->queryString)) {
                $options['query'] = $this->queryString;
            }

            // Set header...
            $this->options['headers']['Content-Type'] = $this->contentType;

            // Do the request...
            if (! $this->shouldMockResponse) {
                $response = $this->client->request($this->method, $this->endPoint, $this->options);
                $response = $response->getBody()->getContents();
            }
            else {
                $response = $this->mockResponse;
            }

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            throw new OrbitCustomException('cURL connection failed', Client::CURL_CONNECT_ERROR_CODE, NULL);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->getCode() === 401) {
                throw new OrbitCustomException('Unautorized access', Client::UNAUTHORIZED_ERROR_CODE, NULL);
            }

            $response = $e->getResponse()->getBody()->getContents();
        } catch(Exception $e) {
            Log::info('ProviderAPI Client [E]: ' . $e->getMessage());

            $response = new \stdClass;
            $response->meta = new \stdClass;
            $response->meta->status = false;
            $response->meta->message = 'Internal server error.';
            $response->result = null;
        }

        return $this->response($response);
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

        $this->options['body'] = $this->buildRequestParam();

        if ($this->shouldMockResponse) {
            $this->mockResponseData();
        }

        return $this->request();
    }
}
