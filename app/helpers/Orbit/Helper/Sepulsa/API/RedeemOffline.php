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
    public function redeem($token, $identifier=null, $mlcode=null, $tries=1)
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

            // If we get unauthorized error, it might be the token is invalid (need refresh)
            // So we need to re-log, and use the new token to do the request.
            if ($e->getCode() === SepulsaClient::UNAUTHORIZED_ERROR_CODE) {

                // Limit the retry.
                if ($tries >= SepulsaClient::MAX_RETRIES) {
                    throw new Exception("Error Processing Request, Tried {$tries} times.", 1);
                }

                $tries++;
                Login::create($this->config)->login()->saveToken();

                // Retry the request
                return $this->redeem($token, $identifier, $mlcode, $tries);

            } else {
                return $e->getMessage();
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

}