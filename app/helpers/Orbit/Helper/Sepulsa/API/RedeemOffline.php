<?php namespace Orbit\Helper\Sepulsa\API;

use Orbit\Helper\Sepulsa\Client as SepulsaClient;
use Orbit\Helper\Sepulsa\API\Login;
use Orbit\Helper\Exception\OrbitCustomException;
use Config;
use Cache;

/**
 * Get Redeem Offline from Sepulsa
 */
class RedeemOffline
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
     * Redeem Offline endpoint
     */
    protected $endpoint = 'partner/voucher/redeem';

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
     * @param string $identifier
     * @param string $mlcode
     */
    public function redeem($token, $indentifier=null, $mlcode=null, $counter=0)
    {
        try {
            $requestParams = [
                'voucher_token' => $token,
                'identifier' => $identifier,
                'mlcode' => $mlcode,
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