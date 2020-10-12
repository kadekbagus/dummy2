<?php namespace Orbit\Helper\DigitalProduct\Response;

trait HasVoucherData
{
    public $voucherData = [];

    /**
     * Parse voucher information from response.
     * Voucher data format from API would be: Voucher code = QV343-Q23123-CGUC2-928SS-0ACGE,Serial Number = 127288910
     *
     * @param  [type] $response [description]
     * @return [type]           [description]
     */
    protected function parseVoucherData($response)
    {
        if (isset($response->voucher)) {
            $voucherData = explode(',', $response->voucher);

            foreach($voucherData as $data) {
                $dataArr = explode('=', $data);
                $voucherDataKey = trim($dataArr[0]);

                $this->voucherData[$voucherDataKey] = isset($dataArr[1])
                    ? trim($dataArr[1]) : null;
            }
        }
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
     * At first on the example (and confluence), ayopay will return something like this:
     * Voucher code = QV343-Q23123-CGUC2-928SS-0ACGE,Serial Number = 127288910
     *
     * but somehow, on testing it can return like below:
     * Serial Number1=T201801036,Voucher Code=E8skSlHzJB3zh1DipuOe
     *
     * Since we don't know exactly what key returned by the ayopay api,
     * we just guessing by the first 13 letters from voucher data key.
     * If match 'serial number', then assume it is the right serial number.
     *
     * @return string the voucher serial number
     */
    public function getSerialNumber()
    {
        $serialNumber = '';

        foreach($this->voucherData as $voucherDataKey => $voucherData) {
            if (substr($voucherDataKey, 0, 13) === 'serial number') {
                $serialNumber = $voucherData;
                break;
            }
        }

        return $serialNumber;
    }
}
