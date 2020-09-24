<?php namespace Orbit\Helper\DigitalProduct\Providers\UPoint\API\Voucher;

use Orbit\Helper\DigitalProduct\Providers\UPoint\Response\Voucher\PurchaseAPIResponse;

/**
 * Purchase API wrapper for ayopay client.
 *
 * @todo  create a proper base api client and response.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseAPI extends UPointVoucherAPI
{
    protected $endPoint = '/repoFeeder/host_to_host/request';

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
            'ref_no' => $this->requestData['trx_id'],
            'item_code' => $this->requestData['item'],
            'timestamp' => $this->requestData['timestamp'],
            'signature' => $this->generateSignature(),
        ];

        return $params;
    }

    protected function mockResponseData()
    {
        $this->randomizeResponseChance[0] = 1;

        if ($this->randomizeResponseChance[0] === 1) {
            $this->mockResponse = json_decode('{
                "status":1,
                "trx_id":"12345678",
                "ref_no":"' . $this->requestData['trx_id'] . '"
            }');
        }
        else {
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
        return new PurchaseAPIResponse($response);
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
            . $this->requestData['trx_id']
            . $this->requestData['item']
            . $this->requestData['timestamp']
            . $this->config['secret_token']
        );
    }
}
