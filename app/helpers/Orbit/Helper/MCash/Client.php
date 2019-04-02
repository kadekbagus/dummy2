<?php namespace Orbit\Helper\MCash;
/**
 * MCash client using Guzzle
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use \GuzzleHttp\Client as Guzzle;
use Orbit\JWT;
use Orbit\Helper\MCash\ConfigSelector;
use Orbit\Helper\Exception\OrbitCustomException;

class Client
{
    const CURL_CONNECT_ERROR_CODE = 8701;
    const UNAUTHORIZED_ERROR_CODE = 8702;
    const MAX_RETRIES = 1;

    /**
     * The main config
     *
     * @var array
     */
    protected $config = [];

    /**
     * @var string endpoint
     */
    protected $endpoint = '';

    /**
     * @var string customQuery
     */
    protected $customQuery = FALSE;

    /**
     * @var array queryString
     */
    protected $queryString = '';

    /**
     * @var Guzzle\Client
     */
    protected $client;

    /**
     * @var array http header
     */
    protected $headers = [];

    /**
     * @var array json request body
     */
    protected $jsonBody = [];

    /**
     * @param array $config
     * @return void
     */
    public function __construct(array $config=[])
    {
        $this->config = $config + $this->config;

        $this->config = ConfigSelector::create($this->config)->getConfig();

        if (empty($this->config['base_uri'])) {
            throw new OrbitCustomException("Base URI is not set", 1);
        }

        $this->client = new Guzzle([
                'base_uri' => $this->config['base_uri']
            ]);
    }

    /**
     * @param array $config
     * @return MCash\Client
     */
    public static function create(array $config=[])
    {
        return new Static($config);
    }

    /**
     * Set the body
     *
     * @param array $body
     * @return MCash\Client
     */
    public function setBody(array $body=[])
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Set the headers
     *
     * @param array $headers
     * @return MCash\Client
     */
    public function setHeaders(array $headers=[])
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Set the jsonBody
     *
     * @param array $jsonBody
     * @return MCash\Client
     */
    public function setJsonBody(array $jsonBody=[])
    {
        $this->jsonBody = $jsonBody;

        return $this;
    }

    /**
     * Set the formParam
     *
     * @param array $formParam
     * @return MCash\Client
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
     * @return MCash\Client
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
     * @return MCash\Client
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
     * @return MCash\Client
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
            $acceptedMethods = ['GET', 'POST'];
            if (! in_array($method, $acceptedMethods)) {
                throw new OrbitCustomException("Invalid HTTP method.", 1);
            }

            $options = [];
            $options['query'] = $this->queryString;
            if ($this->customQuery) {
                $this->endpoint = (! empty($this->queryString)) ? $this->endpoint . '&' . $this->queryString : $this->endpoint;
                unset($options['query']);
            }

            // $options['verify'] = false;

            if (! empty($this->jsonBody)) {
                $options['json'] = $this->jsonBody;
            }

            if (! empty($this->headers)) {
                $options['headers'] = $this->headers;
            }

            $jwt = JWT::encode(['username' => $this->config['auth']['username'], 'iat' => time()], $this->config['auth']['key'], 'HS256');

            $options['headers']['Content-Type'] = 'application/json';
            $options['headers']['Authorization'] = 'Bearer ' . $jwt;

            $response = $this->client->request($method, $this->endpoint, $options);
            $response = $response->getBody()->getContents();

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            throw new OrbitCustomException('cURL connection failed', Client::CURL_CONNECT_ERROR_CODE, NULL);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->getCode() === 401) {
                throw new OrbitCustomException('Unautorized access', Client::UNAUTHORIZED_ERROR_CODE, NULL);
            }

            $response = $e->getResponse()->getBody()->getContents();
        } catch(\Exception $e) {
            \Log::info('MCash Client [E]: ' . $e->getMessage());

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
