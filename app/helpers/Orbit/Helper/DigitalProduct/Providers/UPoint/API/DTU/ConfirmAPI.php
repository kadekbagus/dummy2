<?php namespace Orbit\Helper\DigitalProduct\Providers\UPoint\API\DTU;

use Orbit\Helper\DigitalProduct\Providers\UPoint\API\DTU\UPointDTUAPI;
use Orbit\Helper\DigitalProduct\Providers\UPoint\Response\DTU\ConfirmAPIResponse;

/**
 * Purchase API wrapper for ayopay client.
 *
 * @todo  create a proper base api client and response.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ConfirmAPI extends UPointDTUAPI
{
    protected $endPoint = '/dtu/payment';

    // protected $shouldMockResponse = true;

    /**
     * Build purchase api request param (body).
     * Should return text/xml string.
     *
     * @return [type] [description]
     */
    protected function buildRequestParam()
    {
        return array_merge(
            $this->requestData,
            $this->buildPaymentInfo(),
            [
                'partner_id' => $this->config['partner_id'],
                'signature' => $this->generateSignature(),
            ]
        );
    }

    /**
     * Build payment info data. Later we might need to map payment info based
     * on game name (some game might need special treatment).
     *
     * @return array $paymentInfo
     */
    private function buildPaymentInfo()
    {
        $paymentInfo = [];

        if (isset($this->requestData['payment_info'])) {
            $paymentInfo['payment_info'] = $this->requestData['payment_info'];
        }

        // TODO: Might need to map the payment info based on the game name.
        // switch ($this->requestData['upoint_product_code']) {
        //     case 'free_fire':
        //     case 'arena-of-valor':
        //     case 'call-of-duty':
        //     case 'speed-drifters':
        //         $paymentInfo = [
        //             'packed_role_id' => $this->requestData['packed_role_id'],
        //         ];
        //         break;

        //     case 'mobile-legends':
        //     case 'roh':
        //     case 'life-after':
        //     case 'marvel':
        //         $paymentInfo = [
        //             'username' => $this->requestData['username'],
        //         ];
        //         break;

        //     case 'point-blank':

        //         break;

        //     default:
        //         $paymentInfo = json_decode($this->requestData['payment_info'])
        //         break;
        // }

        return $paymentInfo;
    }

    protected function mockResponseData()
    {
        if ($this->config['mock_response'] === 'success') {
            $this->mockResponse = json_decode('{
                "status":100,
                "status_msg":"OK",
                "trx_id":"' . $this->requestData['trx_id'] . '",
                "t_id":"12345678",
                "info":{
                    "product":"productCode",
                    "item":"itemCode",
                    "amount":"120000",
                    "user_info":"UserInfo",
                    "time":"' . date('Y-m-d H:i:s') . '",
                    "details":{
                        "packed_role_id":"packedRoleId",
                        "server_name":"Eternal Love",
                        "role_name":"arshav"
                    }
                }
            }');
        }
        else if ($this->config['mock_response'] === 'failed') {
            $this->mockResponse = json_decode('{
                "status":200,
                "status_msg":"Bad Request"
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
        return sha1(
            $this->requestData['trx_id']
            . $this->config['secret_token']
        );
    }
}
