<?php namespace Orbit\Helper\OneSignal;
/**
 * OneSignal Notification helper
 *
 * @author Shelgi <shelgi@dominopos.com>
 */
use Exception;
use \GuzzleHttp\Client as Guzzle;
use Orbit\Helper\Exception\OrbitCustomException;

class OneSignal
{
    const API_URL = 'https://onesignal.com/api/v1';

    const CURL_CONNECT_ERROR_CODE = 8701;

    /**
     * The main config
     *
     * @var array
     */
    protected $config = [
        // node host
        'host' => '',
        // node port
        'port' => ''
    ];

    /**
     * @var Guzzle\Client
     */
    protected $client;

    /**
     * @var array
     */
    private $services = [];

    /**
     * @param array $config
     * @return void
     */
    public function __construct(array $config=[])
    {
        $this->config = $config + $this->config;
        if (empty($this->config['app_id']) || empty($this->config['api_key'])) {
            throw new Exception("Config is empty", 1);
        }

        $this->client = new Guzzle();
    }

    /**
     * Set OneSignal config.
     *
     * @param string $applicationId
     */
    public function setConfig($config=[])
    {
        $this->config = $config + $this->config;
    }

    /**
     * Get OneSignal config.
     *
     * @param string $applicationId
     */
    public function getConfig($key)
    {
        if (in_array($key, ['app_id', 'api_key'], true)) {
            return $this->config[$key];
        }

        return $this->config;
    }

    /**
     * Make a custom api request.
     *
     * @param string                      $method  HTTP Method
     * @param string                      $uri     URI template
     * @param array                       $headers
     * @param string|StreamInterface|null $body
     *
     * @throws OneSignalException
     *
     * @return array
     */
    public function request($method, $uri, array $headers = [], $body = null)
    {
        try {
            $param = array_merge([
                'Content-Type' => 'application/json', 'headers' => $headers
            ]);

            if (! empty($body)) {
                $param['json'] = $body;
            }

            $response = $this->client->request($method, self::API_URL.$uri, $param);

            return json_decode($response->getBody()->getContents());
        } catch (Exception $e) {
            throw new OrbitCustomException($e->getMessage(), self::CURL_CONNECT_ERROR_CODE, NULL);
        }
    }

    /**
     * Create required services on the fly.
     *
     * @param string $name
     *
     * @return object
     *
     * @throws OneSignalException If an invalid service name is given
     */
    public function __get($name)
    {
        if (in_array($name, ['apps', 'devices', 'notifications'], true)) {
            if (isset($this->services[$name])) {
                return $this->services[$name];
            }

            $serviceName = __NAMESPACE__.'\\'.ucfirst($name);

            $this->services[$name] = new $serviceName($this, $this->resolverFactory);

            return $this->services[$name];
        }
    }

}
