<?php namespace Orbit\Helper\DigitalProduct\Providers\Woodoos\API;

use Orbit\Helper\DigitalProduct\Providers\Woodoos\Response\PurchaseAPIResponse;

/**
 * Purchase API wrapper for Woodoos client.
 *
 * @todo  create a proper base api client and response.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseAPI extends WoodoosAPI
{
    protected $endPoint = 'giftCardService/activation';

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
            'terminalId' => $this->config['terminal_id'],
            'cashierId' => $this->config['cashier_id'],
            'transactionNumber' => $this->requestData['trx_id'],
            'gencode' => $this->requestData['item_code'],
            'amount' => $this->requestData['amount'],
            // 'billing_id' => $this->requestData['electric_id'],
        ];
    }

    protected function mockResponseData()
    {
        $this->randomizeResponseChance[0] = 1;

        if ($this->randomizeResponseChance[0] === 1) {
            $this->mockResponse = '{
                "isSuccessful":true,
                "referenceNumber":"12345678",
                "previousBalance":123456,"currency":"IDR",
                "amount":123444,
                "balance":1234567,
                "note":"note",
                "expireDate":"20250101",
                "mobileNo":"62123121111",
                "cardNumber":"1234-1234-1234-1234",
                "pinCode":"123456",
                "cardType":"CC",
                "previousPoints":12,
                "addedPoints":12,
                "pointBalance":true,
                "bonusAmount":1234567,
                "redeemedPoints":12,
                "initialBalance":1234567,
                "earliestExpiryDate":"20250101",
                "earliestExpiryAmount":123456,
                "barcodeNumber":"123456789"
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
        return new PurchaseAPIResponse($response);
    }
}
