<?php namespace Orbit\Helper\MCash\API;

use Orbit\Helper\MCash\Client as MCashClient;
use Orbit\Helper\MCash\ConfigSelector;
use Orbit\Helper\MCash\API\Responses\PurchaseResponse;
use Orbit\Helper\Exception\OrbitCustomException;
use Config;

/**
 * MCash API to do a pulsa purchase
 */
class Purchase
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
     * Main endpoint
     */
    protected $endpoint = 'json';

    /**
     * Command to do a Purchase
     */
    protected $command = 'PURCHASE';

    /**
     * Mock data.
     * @var null
     */
    protected $mockData = null;

    /**
     * Login response
     */
    protected $response;

    public function __construct($config=[])
    {
        $this->config = ! empty($config) ? $config : Config::get('orbit.partners_api.mcash');
        $this->client = MCashClient::create($this->config);

        $this->initMockResponse();
    }

    public static function create($config=[])
    {
        return new static($config);
    }

    private function initMockResponse()
    {
        if (! $this->config['is_production']
            && isset($this->config['mock_response'])
            && ! empty($this->config['mock_response'])
        ) {
            $this->mockResponse(
                $this->config['mock_response_data'][
                    $this->config['mock_response']
                ]
            );
        }
    }

    public function mockResponse($data = [])
    {
        $this->mockData = (object) array_merge([
                'status' => 0,
                'message' => 'TRX SUCCESS',
                'data' => (object) [
                    'serial_number' => '12313131',
                ]
            ], $data);

        return $this;
    }

    /**
     * @param string $product - MCash product code (pulsa code)
     * @param string $customer - Customer phone number
     * @param string $partnerTrxid - GTM Transaction ID
     */
    public function doPurchase($product, $customer, $partnerTrxid=null)
    {
    	try {
            if (empty($product)) {
                throw new \Exception("Product code is required", 1);
            }
            if (empty($customer)) {
                throw new \Exception("Customer phone number is required", 1);
            }

            if (! empty($this->mockData)) {
                return new PurchaseResponse($this->mockData);
            }

            $requestParams = [
                'command' => $this->command,
                'product' => $product,
                'customer' => $customer,
                'partner_trxid' => $partnerTrxid,
            ];

            $response = $this->client
            	->setJsonBody($requestParams)
                ->setEndpoint($this->endpoint)
                ->request('POST');

    	} catch (OrbitCustomException $e) {
            $response = (object) [
                'status' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => null,
            ];
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }

        return new PurchaseResponse($response);
    }
}
