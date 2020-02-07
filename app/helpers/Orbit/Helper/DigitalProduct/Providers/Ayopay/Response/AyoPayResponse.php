<?php namespace Orbit\Helper\DigitalProduct\Providers\Ayopay\Response;

use Orbit\Helper\DigitalProduct\Response\BaseResponse;

/**
 * AyoPay base Response mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class AyoPayResponse extends BaseResponse
{
    protected $voucherData = [];

    /**
     * Accept well-formatted xml string...
     *
     * @param [type] $response [description]
     */
    public function __construct($response)
    {
        $this->rawResponse = $response;

        if (is_string($response)) {
            $xmlResponse = @simplexml_load_string($response);

            if ($xmlResponse instanceof \SimpleXMLElement) {
                $response = $xmlResponse;

                $this->parseVoucherData($response);
            }
        }

        parent::__construct($response);
    }

    /**
     * Parse voucher information from response.
     * Voucher data format from API would be: Voucher code = QV343-Q23123-CGUC2-928SS-0ACGE,Serial Number = 127288910
     *
     * @param  [type] $response [description]
     * @return [type]           [description]
     */
    private function parseVoucherData($response)
    {
        if (isset($response->voucher)) {
            $voucherData = explode(',', $response->voucher);

            foreach($voucherData as $data) {
                $dataArr = explode('=', $data);

                if (count($dataArr) === 2) {
                    $this->voucherData[strtolower(trim($dataArr[0]))] = trim($dataArr[1]);
                }
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
     * Just return the raw response from API.
     *
     * @return [type] [description]
     */
    public function getData()
    {
        return $this->rawResponse;
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
