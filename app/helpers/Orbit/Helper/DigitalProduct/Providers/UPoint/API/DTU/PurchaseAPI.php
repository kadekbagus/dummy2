<?php namespace Orbit\Helper\DigitalProduct\Providers\UPoint\API\DTU;

use Orbit\Helper\DigitalProduct\Providers\UPoint\API\DTU\UPointDTUAPI;
use Orbit\Helper\DigitalProduct\Providers\UPoint\Response\DTU\PurchaseAPIResponse;

/**
 * Purchase API wrapper for ayopay client.
 *
 * @todo  create a proper base api client and response.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseAPI extends UPointDTUAPI
{
    protected $endPoint = '/dtu/inquiry';

    protected $shouldMockResponse = true;

    /**
     * Build purchase api request param (body).
     * Should return text/xml string.
     *
     * @return [type] [description]
     */
    protected function buildRequestParam()
    {
        return array_merge($this->requestData, [
            'partner_id' => $this->config['partner_id'],
            'signature' => $this->generateSignature(),
        ]);
    }

    protected function mockResponseData()
    {
        $this->randomizeResponseChance[0] = 1;

        if ($this->randomizeResponseChance[0] === 1) {
            $this->mockResponse = '{
                "status":100,
                "status_msg":"OK",
                "trx_id":"' . $this->requestData['trx_id'] . '",
                "t_id":"12345678",
                "info":{
                    "product":"' . $this->requestData['product'] . '",
                    "item":"' . $this->requestData['item']. '",
                    "amount":"120000",
                    "user_info":' . $this->requestData['user_info']. ',
                    "time":"' . date('Y-m-d H:i:s') . '",
                    "details":{
                        "packed_role_id":"packedRoleId",
                        "server_name":"Eternal Love",
                        "role_name":"arshav"
                    }
                }
            }';
        }
        else {
            $this->mockResponse = '{
                "status":200,
                "status_msg":"Bad Request"
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

    /**
     * Generate pass/auth code for the Purchase request.
     *
     * @return [type] [description]
     */
    private function generateSignature()
    {
        return sha1(
            $this->requestData['trx_id']
            . $this->requestData['product']
            . $this->requestData['item']
            . $this->requestData['user_info']
            . $this->config['secret_token']
        );
    }
}
