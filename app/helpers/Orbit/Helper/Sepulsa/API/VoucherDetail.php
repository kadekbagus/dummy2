<?php namespace Orbit\Helper\Sepulsa\API;

use Orbit\Helper\Sepulsa\Client as SepulsaClient;
use Orbit\Helper\Sepulsa\API\Login;
use Orbit\Helper\Exception\OrbitCustomException;
use Config;
use Cache;

/**
 * Get Voucher detail from Sepulsa
 */
class VoucherDetail
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
     * Voucher detail endpoint
     */
    protected $endpoint = 'partner/voucher/details';

    public function __construct($config=[])
    {
        $this->config = ! empty($config) ? $config : Config::get('orbit.partners_api.sepulsa');
        $this->client = SepulsaClient::create($this->config);
    }

    public static function create($config=[])
    {
        return new static($config);
    }

    /**
     * @param string $token
     */
    public function getDetail($token='', $counter=0)
    {
        try {
            $queryString = [
                'vt' => $token,
            ];

            $requestHeaders = [
                'Content-Type' => 'application/json',
                'Authorization' => Cache::get($this->config['session_key_name'])
            ];

            $response = $this->client
                ->setQueryString($queryString)
                ->setEndpoint($this->endpoint)
                ->setHeaders($requestHeaders)
                ->request('GET');

            return $response;
        } catch (OrbitCustomException $e) {
            if ($e->getCode() === SepulsaClient::UNAUTHORIZED_ERROR_CODE) {
                Login::create($this->config)->login()->saveToken();
                // retries 3 times
                if ($counter > $tries = 3) {
                    throw new Exception("Error Processing Request, Tried {$tries} times.", 1);
                }
                return $this->getList($counter++);
            } else {
                return $e->getMessage();
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

}