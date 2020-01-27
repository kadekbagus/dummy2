<?php namespace Orbit\Helper\DigitalProduct\Providers\Ayopay\Response;

use Orbit\Helper\DigitalProduct\Providers\Ayopay\Response\AyoPayResponse;

/**
 * AyoPay Purchase API Response mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseAPIResponse extends AyoPayResponse
{
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
     * Determine that the purchase is still pending.
     *
     * @return boolean [description]
     */
    public function isPending()
    {
        return ! empty($this->response->data)
            && (isset($this->response->data->status) && 100 === (int) $this->response->data->status);
    }
}
