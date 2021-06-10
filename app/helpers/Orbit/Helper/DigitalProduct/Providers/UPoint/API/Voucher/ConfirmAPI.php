<?php namespace Orbit\Helper\DigitalProduct\Providers\UPoint\API\Voucher;

use Orbit\Helper\DigitalProduct\Providers\UPoint\Response\Voucher\ConfirmAPIResponse;

/**
 * Purchase API wrapper for ayopay client.
 *
 * @todo  create a proper base api client and response.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ConfirmAPI extends UPointVoucherAPI
{
    protected $endPoint = '/repoFeeder/host_to_host/confirm';

    // protected $shouldMockResponse = true;

    /**
     * Build purchase api request param (body).
     * Should return text/xml string.
     *
     * @return [type] [description]
     */
    protected function buildRequestParam()
    {
        $params = [
            'partner_id' => $this->config['partner_id'],
            'trx_id' => $this->requestData['upoint_trx_id'],
            'ref_no' => $this->requestData['trx_id'],
            'status' => $this->requestData['request_status'],
            'signature' => $this->generateSignature(),
        ];

        return $params;
    }

    protected function mockResponseData()
    {
        if ($this->config['mock_response'] === 'success') {
            $this->mockResponse = json_decode('{
                "status":1,
                "trx_id":"12345678",
                "item":[{"name": "voucher","value": "Serial=7211600208;Pins=99997211600208"}]
            }');
        }
        else if ($this->config['mock_response'] === 'failed') {
            $this->mockResponse = json_decode('{
                "status":0,
                "error_code":"E004",
                "error_info":"Out of stock"
            }');
        }
    }

    /**
     * Format/map the api response.
     *
     * @param  [type] $response [description]
     * @return [type]           [description]
     */
    protected function response($response)
    {
        return new ConfirmAPIResponse($response);
    }

    /**
     * Generate pass/auth code for the Purchase request.
     *
     * @return [type] [description]
     */
    private function generateSignature()
    {
        return md5(
            $this->config['partner_id']
            . $this->requestData['upoint_trx_id']
            . $this->requestData['trx_id']
            . $this->requestData['request_status']
            . $this->config['secret_token']
        );
    }
}
