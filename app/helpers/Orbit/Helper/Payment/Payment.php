<?php namespace Orbit\Helper\Payment;
/**
 * Payment
 *
 * @author Shelgi <shelgi@dominopos.com>
 */
use \GuzzleHttp\Client as Guzzle;
use Orbit\Helper\Exception\OrbitCustomException;

class Payment
{
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
     * @var string endpoint
     */
    protected $endpoint = '';

    /**
     * @var string customQuery
     */
    protected $customQuery = FALSE;

    /**
     * @var array post body data
     */
    protected $body = '';

    /**
     * @var array post formParam data
     */
    protected $formParam = '';

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
        if (empty($this->config['host'])) {
            throw new OrbitCustomException("Payment host is not set.", 1);
        }

        $this->client = new Guzzle([
                'base_uri' => $this->config['host'] . (! empty($this->config['port']) ? ':' . $this->config['port'] : '')
            ]);
    }

    /**
     * @param array $config
     * @return MongoDB\Client
     */
    public static function create(array $config=[])
    {
        return new Static($config);
    }

    /**
     * Set the body
     *
     * @param array $body
     * @return MongoDB\Client
     */
    public function setBody(array $body=[])
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Set the formParam
     *
     * @param array $formParam
     * @return MongoDB\Client
     */
    public function setFormParam(array $formParam=[])
    {
        $this->formParam = $formParam;

        return $this;
    }

    /**
     * Set the customQuery
     *
     * @param bool $customQuery
     * @return MongoDB\Client
     */
    public function setCustomQuery($customQuery=FALSE)
    {
        $this->customQuery = $customQuery;

        return $this;
    }

    /**
     * Set the queryString
     *
     * @param array $queryString
     * @return MongoDB\Client
     */
    public function setQueryString(array $queryString=[])
    {
        $this->queryString = $queryString;
        if ($this->customQuery) {
            $this->queryString = http_build_query($queryString);
        }

        return $this;
    }

    /**
     * Set the endpoint
     *
     * @param string endpoint - without trailing slash
     * @return MongoDB\Client
     */
    public function setEndpoint($endpoint='')
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * Make request
     * @param string method - HTTP method
     */
    public function request($method='GET')
    {
        try {
            $acceptedMethods = ['GET', 'POST', 'DELETE', 'PUT'];
            if (! in_array($method, $acceptedMethods)) {
                throw new OrbitCustomException("Invalid HTTP method.", 1);
            }

            $options = [];
            $options['query'] = $this->queryString;
            if ($this->customQuery) {
                $this->endpoint = (! empty($this->queryString)) ? $this->endpoint . '&' . $this->queryString : $this->endpoint;
                unset($options['query']);
            }

            $options['verify'] = false;
            if ($method !== 'GET') {
                $options['body'] = $this->body;
                $options['form_params'] = $this->formParam;
            }

            // return [$method, $this->endpoint, $options, $this->config];

            $response = $this->client->request($method, $this->endpoint, $options);

            return json_decode($response->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            throw new OrbitCustomException('cURL connection failed', Payment::CURL_CONNECT_ERROR_CODE, NULL);
        }
    }
}
