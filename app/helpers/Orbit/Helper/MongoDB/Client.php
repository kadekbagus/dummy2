<?php namespace Orbit\Helper\MongoDB;
/**
 * MongoDB client using Guzzle
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use \GuzzleHttp\Client as Guzzle;

class Client
{
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
     * @var array post body data
     */
    protected $body = '';

    /**
     * @var array post multipart data
     */
    protected $multipart = '';

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
            throw new Exception("Nodejs host is not set.", 1);
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
     * Set the multipart
     *
     * @param array $multipart
     * @return MongoDB\Client
     */
    public function setMultipart(array $multipart=[])
    {
        $this->multipart = $multipart;

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
        $acceptedMethods = ['GET', 'POST', 'DELETE', 'PUT'];
        if (! in_array($method, $acceptedMethods)) {
            throw new Exception("Invalid HTTP method.", 1);
        }

        $response = $this->client->request($method, $this->endpoint, [
            'query' => $this->queryString,
            'body' => $this->body,
            'multipart' => $this->multipart
        ]);

        return json_decode($response->getBody()->getContents());
    }
}
