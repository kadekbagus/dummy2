<?php

namespace Orbit\Helper\MCash\API\BPJSBill\Response;

use Orbit\Helper\MCash\API\BillResponse;

/**
 * Inquiry response...
 *
 * @author Budi <budi@gotomalls.com>
 */
class InquiryResponse extends BillResponse
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
            // 'bill_id' => $this->data->data->billing_id,
            'customer_name' => $this->data->data->customer_name,
            'period' => $this->data->data->period,
            'period_name' => $this->data->data->period_name,
            'meter_start' => $this->data->data->meter_start,
            'meter_end' => $this->data->data->meter_end,
            'usage' => $this->data->data->usage,
            'amount' => $this->data->amount,
            'admin_fee' => $this->data->data->admin_fee,
            'total' => $this->data->total,
            'receipt' => $this->parseReceiptInfo(
                $this->data->data->receipt
            ),
        ];
    }

    protected function parseReceiptInfo($receipt)
    {
        // Manually explode and loop since we might find empty value after
        // exploding with '|'.
        $receipts = [
            'header' => $receipt->header,
            'footer' => $receipt->footer,
            'info' => [],
        ];

        $receiptItems = explode('|', $receipt->info);

        foreach($receiptItems as $receiptItem) {

            if (empty($receiptItem)) {
                continue;
            }

            $item = explode(':', $receiptItem);

            if (count($item) >= 2) {
                $receipts['info'][] = [
                    'label' => $item[0],
                    'value' => join('', array_slice($item, 0 - (count($item)-1)))
                ];
            }
        }

        return $receipts;
    }
}
