<?php namespace Orbit\Helper\DigitalProduct\Providers\Ayopay\API;

use Orbit\Helper\DigitalProduct\Providers\Ayopay\API\AyoPayAPI;
use Orbit\Helper\DigitalProduct\Providers\Ayopay\Response\StatusAPIResponse;

/**
 * Status API wrapper for ayopay client.
 *
 * @todo  create a proper base api client and response.
 *
 * @author Budi <budi@gotomalls.com>
 */
class StatusAPI extends AyoPayAPI
{
    protected $shouldMockResponse = true;

    /**
     * Build purchase api request param (body).
     * Should return text/xml string.
     *
     * @return [type] [description]
     */
    protected function buildRequestParam()
    {
        $xmlFormat = "<?xml version="1.0"?>
            <ayopay>
                <function>Requery XML</function>
                <id>{$this->config['id']}</id>
                <trx>{$this->requestData['payment_transaction_id']}</trx>
                <pwd>{$this->generatePassword()}</pwd>
                <productcode>{$this->requestData['product_code']}</productcode>
            </ayopay>";
    }

    /**
     * Generate pass/auth code for the Purchase request.
     *
     * @return [type] [description]
     */
    private function generatePassword()
    {
        $authCode = $this->requestData['payment_transaction_id']
            . $this->requestData['product_code']
            . $this->config['id']
            . $this->config['msg'];

        return sha1($authCode);
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

    protected function mockResponseData()
    {
        $randomResponse = shuffle($this->randomizeResponseChance);

        if (true) {
            $this->mockResponse = "<?xml version="1.0"?>
                <ayopay>
                    <trx_ayopay>IP01000533</trx_ayopay>
                    <voucher>Serial Number=CCAC997201516025,Security code=997201516025</voucher>
                    <status>100</status>
                    <message>Sukses</message>
                </ayopay>";
        }
        else {
            $this->mockResponse = "<?xml version="1.0"?>
                <ayopay>
                    <trx_ayopay>IP01000533</trx_ayopay>
                    <voucher>Serial Number=CCAC997201516025,Security code=997201516025</voucher>
                    <status>500</status>
                    <message>Gagal</message>
                </ayopay>";
        }
    }
}
