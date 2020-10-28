<?php namespace Orbit\Helper\DigitalProduct\Providers\Woodoos\API;

use Orbit\Helper\DigitalProduct\Providers\Woodoos\Response\StatusAPIResponse;

/**
 * Purchase API wrapper for Woodoos client.
 *
 * @todo  create a proper base api client and response.
 *
 * @author Budi <budi@gotomalls.com>
 */
class StatusAPI extends WoodoosAPI
{
    protected $endPoint = 'giftCardService/queryTransactions';

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
        $this->randomizeResponseChance[0] = 1;

        if ($this->randomizeResponseChance[0] === 1) {
            $this->mockResponse = '{
                "isSuccessful":true,
                "cardNumber":"1234-1234-1234-1234",
                "cardStatus":"IN USE",
                "expiryDate":"2022-07-07",
                "cardBalance":800,
                "transactionRecord":[]
            }';
        }
        else if ($this->randomizeResponseChance[0] === 2) {
            $this->mockResponse = '{
                "isSuccessful":true,
                "referenceNumber":"12345678",
                "mobileNo":"62123121111",
                "cardNumber":"",
                "pinCode":"123456"
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
        return new StatusAPIResponse($response);
    }
}
