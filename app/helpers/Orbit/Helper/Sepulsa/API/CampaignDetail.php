<?php namespace Orbit\Helper\Sepulsa\API;

use Orbit\Helper\Sepulsa\Client as SepulsaClient;
use Orbit\Helper\Sepulsa\API\Login;
use Orbit\Helper\Exception\OrbitCustomException;
use Config;
use Cache;

/**
 * Get Campaign detail from Sepulsa
 */
class CampaignDetail
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
     * Campaign detail endpoint
     * partner/campaign/{campaignId}/{partnerId}
     */
    protected $endpoint = 'partner/campaign/%s/%s';

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
     * @param int $campaignId
     */
    public function getDetail($campaignId='', $counter=0)
    {
        try {
            if (empty($campaignId)) {
                throw new Exception("Campaign ID is required", 1);
            }

            $requestHeaders = [
                'Content-Type' => 'application/json',
                'Authorization' => Cache::get($this->config['session_key_name'])
            ];

            $response = $this->client
                ->setEndpoint(sprintf($this->endpoint, $campaignId, $this->config['partner_id']))
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