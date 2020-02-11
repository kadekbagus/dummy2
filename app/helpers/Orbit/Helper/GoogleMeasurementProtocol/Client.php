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
     * @var array userAgent
     * User agent. Needs to be rewritten because of "Bot Filtering" options
     * in Google Analytics.
     */
    protected $userAgent = '';

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

        $this->userAgent = 'Mozilla/5.0 (Linux; Android 4.0.4; Galaxy Nexus Build/IMM76B) AppleWebKit/535.19 (KHTML, like Gecko) Chrome/18.0.1025.133 Mobile Safari/535.19';
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

        // remove empty keys
        $this->queryString = array_filter($this->queryString);

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

            // validate other parameters (required: t, ec, ea)
            if (! isset($this->queryString['t']) || empty($this->queryString['t'])) {
                throw new OrbitCustomException("Hit Type (t) parameter is required", 1);
            }

            if ($this->queryString['t'] == 'event') {
                if (! isset($this->queryString['ec']) || empty($this->queryString['ec'])) {
                    throw new OrbitCustomException("Event Category (ec) parameter is required", 1);
                }
                if (! isset($this->queryString['ea']) || empty($this->queryString['ea'])) {
                    throw new OrbitCustomException("Event Action (ea) parameter is required", 1);
                }
            }

            if (! isset($this->queryString['cid']) || empty($this->queryString['cid'])) {
                $this->queryString['cid'] = time(); // randomize cid to prevent 500 request limit /session/day
            }

            $options = [];
            $options['query'] = $this->queryString;
            $options['headers']['User-Agent'] = $this->userAgent;

            // $options['verify'] = false;

            $response = $this->client->request('POST', $this->endpoint, $options);

            if ($response->getStatusCode() != 200) {
                \Log::info('GoogleMeasurementProtocol Client Non 200 [E]: ' . serialize($response));
            }

            $response = $response->getBody()->getContents();

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            \Log::info('GoogleMeasurementProtocol Client Connect [E]: ' . $e->getMessage());
            throw new OrbitCustomException('cURL connection failed', Client::CURL_CONNECT_ERROR_CODE, NULL);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            \Log::info('GoogleMeasurementProtocol Client Request [E]: ' . $e->getMessage());
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
