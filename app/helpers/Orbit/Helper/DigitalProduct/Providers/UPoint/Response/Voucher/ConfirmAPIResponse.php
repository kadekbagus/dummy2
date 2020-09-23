<?php namespace Orbit\Helper\DigitalProduct\Providers\UPoint\Response\Voucher;

/**
 * AyoPay Purchase API Response mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ConfirmAPIResponse extends PurchaseAPIResponse
{
    protected $voucherData = [];

    public function __construct($response)
    {
        parent::__construct($response);

        $this->parseVoucherData();
    }

    private function parseVoucherData()
    {
        if (isset($this->response->data->item)) {
            $itemValues = explode(';', $this->response->data->item[0]->value);

            foreach($itemValues as $itemValue) {
                $values =  explode('=', $itemValue);
                $this->voucherData[$values[0]] = $values[1];
            }
        }
    }

    public function getVoucherData()
    {
        return $this->voucherData;
    }

    public function getSerialNumber()
    {
        return array_filter($this->voucherData, function($key) {
            return stripos($key, 'serial') !== false;
        }, ARRAY_FILTER_USE_KEY);
    }
}
