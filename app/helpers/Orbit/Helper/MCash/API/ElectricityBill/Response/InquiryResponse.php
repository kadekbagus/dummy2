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
            'bill_id' => $this->data->data->billing_id,
            'customer_name' => $this->data->data->customer_name,
            'period' => $this->data->data->period,
            'amount' => $this->data->amount,
            'admin_fee' => $this->data->data->admin_fee,
            'total' => $this->data->total,
            'receipt' => $this->parseReceiptInfo(
                $this->data->data->receipt->info
            ),
        ];
    }

    protected function parseReceiptInfo($receiptInfo)
    {
        // Manually explode and loop since we might find empty value after
        // exploding with '|'.
        $receipts = [];
        $receiptItems = explode('|', $receiptInfo);

        foreach($receiptItems as $receiptItem) {

            if (empty($receiptItem)) {
                continue;
            }

            $item = explode(':', $receiptItem);

            if (count($item) >= 2) {
                $receipts[] = [$item[0] => join('', array_slice($item, 0 - (count($item)-1)))];
            }
        }

        return $receipts;
    }
}
