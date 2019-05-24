<?php namespace Orbit\Helper\GoogleMeasurementProtocol;
/**
 * GoogleMeasurementProtocol client using Guzzle
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use \GuzzleHttp\Client as Guzzle;
use Orbit\Helper\Exception\OrbitCustomException;

class Client
{
    /**
     * The main config
     *
     * @var array
     */
    protected $config = [];

    /**
     * @var string endpoint
     */
    protected $endpoint = '/collect';

    /**
     * @var array queryString
     */
    protected $queryString = '';

    /**
     * @var Guzzle\Client
     */
    protected $client;

    /**
     * @param array $config
     * @return void
     */
    public function __construct(array $config=[])
    {
        $this->config = $config + $this->config;

        if (empty($this->config['base_uri'])) {
            throw new OrbitCustomException("Base URI is not set", 1);
        }

        $this->client = new Guzzle([
                'base_uri' => $this->config['base_uri']
            ]);

        $this->queryString['tid'] = $this->config['tid'];
        $this->queryString['v'] = '1';
        $this->queryString['t'] = 'event';
        $this->queryString['cid'] = '555'; // default cid (anonymous)
    }

    /**
     * @param array $config
     * @return GoogleMeasurementProtocol\Client
     */
    public static function create(array $config=[])
    {
        return new Static($config);
    }

    /**
     * Set the queryString
     *
     * @param array $queryString
     * @return GoogleMeasurementProtocol\Client
     */
    public function setQueryString(array $queryString=[])
    {
        $this->queryString = $queryString + $this->queryString;

        return $this;
    }

    /**
     * Make request
     * @param string method - HTTP method
     */
    public function request()
    {
        try {
            if (! $this->config['is_enabled']) {
                return;
            }

            // validate other parameters (required: ec, ea)
            if (! isset($this->queryString['ec']) || empty($this->queryString['ec'])) {
                throw new OrbitCustomException("Event Category (ec) parameter is required", 1);
            }
            if (! isset($this->queryString['ea']) || empty($this->queryString['ea'])) {
                throw new OrbitCustomException("Event Action (ea) parameter is required", 1);
            }

            $options = [];
            $options['query'] = $this->queryString;

            // $options['verify'] = false;

            $response = $this->client->request('POST', $this->endpoint, $options);
            $response = $response->getBody()->getContents();

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            throw new OrbitCustomException('cURL connection failed', Client::CURL_CONNECT_ERROR_CODE, NULL);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->getCode() === 401) {
                throw new OrbitCustomException('Unautorized access', Client::UNAUTHORIZED_ERROR_CODE, NULL);
            }

            $response = $e->getResponse()->getBody()->getContents();
        } catch(\Exception $e) {
            \Log::info('GoogleMeasurementProtocol Client [E]: ' . $e->getMessage());

            $response = new \stdClass;
            $response->meta = new \stdClass;
            $response->meta->status = false;
            $response->meta->message = 'Internal server error.';
            $response->result = null;

            return $response;
        }

        return json_decode($response);
    }
}
