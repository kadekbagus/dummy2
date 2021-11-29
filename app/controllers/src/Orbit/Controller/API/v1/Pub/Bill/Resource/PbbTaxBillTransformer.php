<?php

namespace Orbit\Controller\API\v1\Pub\Bill\Resource;

trait PbbTaxBillTransformer
{
    private function transformPbbTaxBillInformation($notes)
    {
        $notes = unserialize($notes);

        $inquiry = isset($notes['inquiry']) ? $notes['inquiry'] : null;

        return empty($inquiry) ? null : [
            'billing_id' => $inquiry->data->billing_id,
            'customer_name' => $inquiry->data->customer_name,
            'period' => $inquiry->data->period,
            'amount' => $inquiry->amount,
            'admin_fee' => $inquiry->data->admin_fee,
            'total' => $inquiry->total,
            'receipt' => $this->parsePbbTaxBillReceipt(
                $inquiry->data->receipt->info
            ),
        ];
    }

    private function parsePbbTaxBillReceipt($receiptInfo)
    {
        // Manually explode and loop since we might find empty value after
        // exploding with '|'.
        // @todo might use array_values to filter empty items.
        $receipts = [];
        $receiptItems = explode('|', $receiptInfo);

        foreach($receiptItems as $receiptItem) {

            if (empty($receiptItem)) {
                continue;
            }

            $item = explode(':', $receiptItem);

            if (count($item) >= 2) {
                $receipts[] = [
                    'label' => $item[0],
                    'value' => join('', array_slice($item, 0 - (count($item)-1)))
                ];
            }
        }

        return $receipts;
    }
}
