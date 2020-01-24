<?php namespace Orbit\Helper\DigitalProduct\Providers\Ayopay\Response;

use Orbit\Helper\DigitalProduct\Response\BaseResponse;

/**
 * AyoPay Purchase API Response mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseAPIResponse extends BaseResponse
{
    private $voucherData = [];

    /**
     * Accept well-formatted xml string...
     *
     * @param [type] $response [description]
     */
    public function __construct($response)
    {
        if (is_string($response)) {
            $xmlResponse = simplexml_load_string($response);

            if ($xmlResponse instanceof \SimpleXMLElement) {
                $response = (object) json_decode(json_encode($xmlResponse), true);

                $this->parseVoucherData($response);
            }
        }

        parent::__construct($response);
    }

    /**
     * Parse voucher information from response.
     *
     * @param  [type] $response [description]
     * @return [type]           [description]
     */
    private function parseVoucherData($response)
    {
        if (isset($response->voucher)) {
            $voucherData = explode(',', $response->voucher);

            foreach($voucherData as $key => $data) {
                $this->voucherData[strtolower($key)] = $data;
            }
        }
    }

    /**
     * Determine that the response is success.
     *
     * @return boolean [description]
     */
    public function isSuccess()
    {
        return null !== $this->response->data
            && (isset($this->response->data->status) && 100 === (int) $this->response->data->status)
            && (isset($this->response->data->message) && strtolower($this->response->data->message) === 'sukses');
    }

    /**
     * Determine that the purchase is still pending.
     *
     * @return boolean [description]
     */
    public function isPending()
    {
        return ! empty($this->response->data)
            && (isset($this->response->data->status) && 100 === (int) $this->response->data->status);
    }

    /**
     * Get the voucher information from the response.
     * @return [type] [description]
     */
    public function getVoucherData()
    {
        return $this->voucherData;
    }

    /**
     * Get the voucher code from response/voucher data.
     * @return [type] [description]
     */
    public function getVoucherCode()
    {
        return isset($this->voucherData['voucher code'])
            ? $this->voucherData['voucher code']
            : '';
    }

    /**
     * Get serial number from the response/voucher data.
     * @return [type] [description]
     */
    public function getSerialNumber()
    {
        return isset($this->voucherData['serial number'])
            ? $this->voucherData['serial number']
            : '';
    }

    /**
     * Don't support retry at the moment.
     *
     * @param  integer $retry [description]
     * @return [type]         [description]
     */
    public function shouldRetry($retry = 1)
    {
        return false;
    }

    /**
     * Get the failure message from AyoPay.
     *
     * @return [type] [description]
     */
    public function getFailureMessage()
    {
        return $this->response->message;
    }
}
