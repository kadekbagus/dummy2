<?php namespace Orbit\Helper\Sepulsa\API;

use Orbit\Helper\Sepulsa\Client as SepulsaClient;
use Orbit\Helper\Sepulsa\API\Login;
use Orbit\Helper\Exception\OrbitCustomException;
use Config;
use Cache;

/**
 * Get Voucher List from Sepulsa
 */
class RedeemOnline
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
     * Voucher list endpoint
     */
    protected $endpoint = 'partner/voucher/redeemonline';

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
     * @param string $searchQuery
     * @param int $recordPerPage
     * @param array $filter
     * @param int $page
     */
    public function redeem($token, $code, $counter=0)
    {
        try {
            $requestParams = [
                'voucher_token' => $token,
                'code' => $code,
            ];

            $requestHeaders = [
                'Content-Type' => 'application/json',
                'Authorization' => Cache::get($this->config['session_key_name'])
            ];

            $response = $this->client
                ->setJsonBody($requestParams)
                ->setEndpoint($this->endpoint)
                ->setHeaders($requestHeaders)
                ->request('POST');

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