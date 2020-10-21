<?php namespace Orbit\Helper\DigitalProduct\Providers\Woodoos\API;

use Orbit\Helper\DigitalProduct\Providers\Woodoos\Response\ReversalAPIResponse;

/**
 * Purchase API wrapper for Woodoos client.
 *
 * @todo  create a proper base api client and response.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReversalAPI extends WoodoosAPI
{
    protected $endPoint = 'giftCardService/reversal';

    // protected $shouldMockResponse = true;

    /**
     * Build purchase api request param (body).
     * Should return text/xml string.
     *
     * @return [type] [description]
     */
    protected function buildRequestParam()
    {
        return [
            'merchantId' => $this->config['merchant_id'],
            'transactionNumber' => $this->requestData['trx_id'],
        ];
    }

    protected function mockResponseData()
    {
        $this->randomizeResponseChance[0] = 0;

        if ($this->randomizeResponseChance[0] === 1) {
            $this->mockResponse = '{
                "isSuccessful":true,
                "errorCode":null,
                "errorMessage":null,
                "handlingFee":100,
                "previousBalance":100,
                "balance":0,
                "note":""
            }';
        }
        else {
            $this->mockResponse = '{
                "isSuccessful":false,
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
        return new ReversalAPIResponse($response);
    }
}
