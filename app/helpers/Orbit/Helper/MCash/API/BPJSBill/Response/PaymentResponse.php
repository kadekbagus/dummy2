<?php

namespace Orbit\Helper\MCash\API\BPJSBill\Response;

use Orbit\Helper\MCash\API\BillResponse;

/**
 * Pay command response...
 *
 * @author Budi <budi@gotomalls.com>
 */
class PaymentResponse extends BillResponse
{
    public function hasBillingInformation()
    {
        return isset($this->data->data)
            && isset($this->data->data->customer_name);
    }

    protected function parseResponse()
    {
        if (! $this->hasBillingInformation()) {
            return;
        }

        $this->billInformation = (object) [
            'inquiry_id' => $this->data->inquiry_id,
            'billing_id' => $this->data->data->billing_id,
            'customer_name' => $this->data->data->customer_name,
            'period' => $this->data->data->period,
            'amount' => $this->data->data->amount,
            'admin_fee' => $this->data->data->admin_fee,
            'receipt' => $this->data->data->receipt,
            'customer_info' => $this->parseReceiptInfo(
                $this->data->data->receipt->info
            ),
        ];
    }

    /**
     * Reformat string
     *
     * 'Name: Customer|kwh: 1800'
     *
     * into
     *
     * ['Name' => 'Customer', 'kwh' => 1800]
     *
     * @param  [type] $receiptInfo [description]
     * @return [type]              [description]
     */
    private function parseReceiptInfo($receiptInfo)
    {
        return array_map(function($info) {
            $item = explode(':', $info);

            return count($item) >= 2
                ? [
                    'label' => $item[0],
                    'value' => join('', array_slice($item, 0 - (count($item)-1)))
                ]
                : join(':', $item);

        }, explode('|', $receiptInfo));
    }
}
