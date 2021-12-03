<?php namespace Orbit\Helper\MCash\API;

use Config;
use Illuminate\Support\Facades\Log;
use Orbit\Helper\Exception\OrbitCustomException;
use Orbit\Helper\MCash\API\BillInterface;
use Orbit\Helper\MCash\API\Responses\InquiryResponse;
use Orbit\Helper\MCash\Client as MCashClient;
use Orbit\Helper\MCash\ConfigSelector;

/**
 * MCash API wrapper to do electricity bill purchase.
 *
 * @author Budi <budi@gotomalls.com>
 */
abstract class Bill implements BillInterface
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
     * Command to do inquiry
     */
    protected $inquiryCommand = 'INQUIRY';

    /**
     * Command to do payment.
     * @var string
     */
    protected $payCommand = 'PAY';

    /**
     * Mock data.
     * @var null
     */
    protected $mockData = null;

    /**
     * Login response
     */
    protected $response;

    protected $billType = null;

    const ELECTRICITY_BILL = 'electricity_bill';
    const PDAM_BILL = 'pdam_bill';
    const PBB_TAX_BILL = 'pbb_tax';
    const BPJS_BILL = 'bpjs_bill';
    const ISP_BILL = 'internet_provider_bill';

    public function __construct($config=[])
    {
        $this->config = Config::get('orbit.partners_api.mcash', []);
        $this->config = array_merge($this->config, $config);
        $this->config['mock_bill_response'] = Config::get(
            'orbit.partners_api.mock_bill_response.' . $this->billType,
            false
        );
        $this->client = MCashClient::create($this->config);
    }

    public static function create($config=[])
    {
        return new static($config);
    }

    protected function initMockResponse($command = '')
    {
        if ($this->config['is_production']) {
            return;
        }

        if (! $this->config['mock_bill_response']) {
            return;
        }

        $mock = $this->config['mock_bill_response'][$command];

        if (! $mock) {
            return;
        }

        $mockMethod = 'mock' . ucfirst($command) . ucfirst($mock) . 'Response';

        $this->{$mockMethod}();
    }

    public static function getBillTypeIds()
    {
        return [
            self::ELECTRICITY_BILL,
            self::PDAM_BILL,
            self::PBB_TAX_BILL,
            self::BPJS_BILL,
            self::ISP_BILL,
        ];
    }

    public static function getBillSettingName()
    {
        return [
            self::ELECTRICITY_BILL => 'enable_electricity_bill_page',
            self::PDAM_BILL => 'enable_pdam_bill_page',
            self::PBB_TAX_BILL => 'enable_pbb_tax_page',
            self::BPJS_BILL => 'enable_bpjs_bill_page',
            self::ISP_BILL => 'enable_internet_provider_bill_page',
        ];
    }

    protected function log($message, $type = 'info')
    {
        Log::{$type}(strtoupper($this->billType) . ': ' . $message);
    }

    abstract public function inquiry($params = []);

    abstract protected function inquiryResponse($responseData);

    abstract public function pay($params = []);

    abstract protected function payResponse($responseData);

    public function mockResponse($data = [])
    {
        $this->mockData = (object) $data;

        return $this;
    }
}
