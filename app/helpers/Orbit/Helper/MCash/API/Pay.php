<?php namespace Orbit\Helper\MCash\API;

use Config;
use Orbit\Helper\Exception\OrbitCustomException;
use Orbit\Helper\MCash\API\Responses\PayResponse;
use Orbit\Helper\MCash\Client as MCashClient;
use Orbit\Helper\MCash\ConfigSelector;

/**
 * MCash API to do a pulsa purchase
 */
class Pay
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
    protected $command = 'PAY';

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
        $this->config = Config::get('orbit.partners_api.mcash', []);
        $this->config = array_merge($this->config, $config);
        $this->config['mock_response'] = Config::get('orbit.partners_api.mock_response', false);
        $this->client = MCashClient::create($this->config);

        $this->initMockResponse();
    }

    public static function create($config=[])
    {
        return new static($config);
    }

    private function initMockResponse()
    {
        if ($this->config['is_production']) {
            return;
        }

        if ($this->config['mock_response'] === 'success') {
            $this->mockSuccessResponse();
        }
        else if ($this->config['mock_response'] === 'failed') {
            $this->mockFailedResponse();
        }
    }

    public function mockSuccessResponse()
    {
        $data = [
            'status' => 0,
            'message' => 'TRX SUCCESS',
            'data' => (object) [
                'serial_number' => "12313131",
            ],
        ];

        if (isset($this->config['product_type'])) {
            switch ($this->config['product_type']) {
                case 'electricity':
                    $data['data'] = (object) [
                        'serial_number' =>  '1231231231231*Bambang*500000*2200*240',
                    ];
                    break;

                case 'electricity_bills':
                    $data = [
                        "status" => 0,
                        "message" => "Inquiry success. PLN-551000490568 SUGITO BA. Amount: Rp.73819, admin: Rp.1000, total: Rp.74819",
                        "created_at" => "0001-01-01T00:00:00Z",
                        "inquiry_id" => 20210027,
                        "amount" => 73819,
                        "total" => 74819,
                        "pending" => 0,
                        "data" => (object) [
                            "customer_name" => "SUGITO BA",
                            "admin_fee" => 1000,
                            "amount" => 73819,
                            "period" => 1,
                            "billing_id" => "551000490568",
                            "receipt" => (object) [
                                "header" => "",
                                "footer" => "<br>",
                                "info" => "IDPEL: 551000490568|NAMA: SUGITO BA|JML BLN TAG: 01|BL/TH: Nov21|JML TAG PLN: 76.319|"
                            ],
                        ],
                        "balance" => 36077242,
                    ];
                    break;

                default:
                    break;
            }
        }

        return $this->mockResponse($data);
    }

    public function mockFailedResponse()
    {
        return $this->mockResponse([
            "status" => 611,
            "message" => "[ TAGIHAN SUDAH TERBAYAR ]",
            "created_at" => "0001-01-01T00:00:00Z",
            "inquiry_id" => 20303407,
            "pending" => 0
        ]);
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
    public function doPurchase($params = [])
    {
        try {
            if (empty($product)) {
                throw new \Exception("Product code is required", 1);
            }
            if (empty($customer)) {
                throw new \Exception("Customer phone number is required", 1);
            }

            if (! empty($this->mockData)) {
                return new PayResponse($this->mockData);
            }

            $requestParams = [
                'command' => $this->command,
                'product' => $params['product'],
                'customer' => $params['customer'],
                'partner_trxid' => $params['partnerTrxid'],
                'amount' => $params['amount'],
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

        return new PayResponse($response);
    }
}
