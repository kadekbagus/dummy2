<?php namespace Orbit\Helper\DigitalProduct\Providers\Woodoos\API;

use Orbit\Helper\DigitalProduct\Providers\Woodoos\Response\ConfirmAPIResponse;

/**
 * Purchase API wrapper for Woodoos client.
 *
 * @todo  create a proper base api client and response.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ConfirmAPI extends WoodoosAPI
{
    protected $endPoint = 'giftCardService/confirmTransaction';

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
            'merchantId' => $this->config['merchant_id'],
            'terminalId' => $this->config['terminal_id'],
            'cashierId' => $this->config['cashier_id'],
            // 'passphrase' => $this->config['passphrase'],
            'referenceNumber' => $this->requestData['ref_number'],
        ];

        return $params;
    }

    protected function mockResponseData()
    {
        $this->randomizeResponseChance[0] = 1;

        if ($this->randomizeResponseChance[0] === 1) {
            $this->mockResponse = '{
                "isSuccessful":true,
                "referenceNumber":"12345678",
                "balance":1234567,
                "note":"note",
                "pinCode":"123456",
                "pointBalance":true
            }';
        }
        else {
            $this->mockResponse = '{
                "errorCode":"500",
                "errorMessage":"Internal Server Error"
            }';
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
}
