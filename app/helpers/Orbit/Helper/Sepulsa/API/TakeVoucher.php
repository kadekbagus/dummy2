<?php namespace Orbit\Helper\Sepulsa\API;

use Orbit\Helper\Sepulsa\Client as SepulsaClient;
use Orbit\Helper\Sepulsa\API\Login;
use Orbit\Helper\Exception\OrbitCustomException;
use Config;
use Cache;

/**
 * Taken Voucher from Sepulsa
 */
class TakeVoucher
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
     * Taking voucher endpoint
     */
    protected $endpoint = 'partner/voucher/taken';

    const MAX_TRIES = 3;

    public function __construct($config = [])
    {
        $this->config = ! empty($config) ? $config : Config::get('orbit.partners_api.sepulsa');
        $this->client = SepulsaClient::create($this->config);
    }

    public static function create($config = [])
    {
        return new static($config);
    }

    /**
     * @param string $trx_id
     * @param array $tokens = array(
     *           ['token' => '$2y$10$UoqM6KpZLwCgJIDGMZMgt.JvOBfOOIXTIW1O7lpLSDdIboRjwlcHS'],
     *           ['token' => '$2y$10$UoqM6KpZLwCgJIDGMZMgt.JvOBfOOIXTIW1O7lpLSDdIboRjwlxxx']
     *      )
     * @param array $filter
     * @param int $page
     */
    public function take($trxId, $tokens = [], $identifier = null, $tries = 1)
    {
        try {
            $requestParams = [
                'trx_id' => $trxId,
                'tokens' => $tokens,
                'identifier' => $identifier,
                'partnerid' => $this->config['partner_id'],
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
                if ($tries >= self::MAX_TRIES) {
                    throw new Exception("Error Processing Request, Tried {$tries} times.", 1);
                }

                $tries++;
                Login::create($this->config)->login()->saveToken();

                // Retry the request
                return $this->take($trxId, $tokens, $identifier, $tries);

            } else {
                return $e->getMessage();
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
