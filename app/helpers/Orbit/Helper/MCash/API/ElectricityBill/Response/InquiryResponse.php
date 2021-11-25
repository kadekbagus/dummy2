<?php

namespace Orbit\Helper\MCash\API\ElectricityBill\Response;

use Orbit\Helper\MCash\API\BillResponse;

/**
 * Inquiry response...
 *
 * @author Budi <budi@gotomalls.com>
 */
class InquiryResponse extends BillResponse
{
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
            'amount' => $this->data->amount,
            'admin_fee' => $this->data->data->admin_fee,
            'total' => $this->data->total,
            'receipt' => $this->data->data->receipt,
            'customer_info' => $this->parseReceiptInfo(
                $this->data->data->receipt->info
            ),
        ];
    }

    protected function parseReceiptInfo($info)
    {
        return (object) explode('|', $info);
    }
}
