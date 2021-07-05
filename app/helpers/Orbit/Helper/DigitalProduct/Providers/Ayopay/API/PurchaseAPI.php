<?php namespace Orbit\Helper\DigitalProduct\Providers\Ayopay\API;

use Orbit\Helper\DigitalProduct\Providers\Ayopay\API\AyoPayAPI;
use Orbit\Helper\DigitalProduct\Providers\Ayopay\Response\PurchaseAPIResponse;

/**
 * Purchase API wrapper for ayopay client.
 *
 * @todo  create a proper base api client and response.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseAPI extends AyoPayAPI
{
    /**
     * Build purchase api request param (body).
     * Should return text/xml string.
     *
     * @return [type] [description]
     */
    protected function buildRequestParam()
    {
        return "<?xml version=\"1.0\"?>
            <ayopay>
                <function>Request XML</function>
                <id>{$this->config['id']}</id>
                <trx>{$this->requestData['payment_transaction_id']}</trx>
                <pwd>{$this->generatePassword()}</pwd>
                <productcode>{$this->requestData['product_code']}</productcode>
            </ayopay>";
    }

    protected function mockResponseData()
    {
        if ($this->config['mock_response'] === 'success') {
            $this->mockResponse = "<?xml version='1.0'?>
                <ayopay>
                    <trx_ayopay>46737726</trx_ayopay>
                    <saldo>8100</saldo>
                    <voucher>Voucher code = QV343-Q23123-CGUC2-928SS-0ACGE,Serial Number = 127288910</voucher>
                    <status>100</status>
                    <message>Sukses</message>
                </ayopay>";
        }
        else if ($this->config['mock_response'] === 'failed') {
            $this->mockResponse = "<?xml version='1.0'?>
                <ayopay>
                    <status>500</status>
                    <message>Gagal</message>
                </ayopay>";
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
    private function generatePassword()
    {
        $authCode = $this->requestData['payment_transaction_id']
            . $this->config['id']
            . $this->config['msg'];

        return sha1($authCode);
    }
}
