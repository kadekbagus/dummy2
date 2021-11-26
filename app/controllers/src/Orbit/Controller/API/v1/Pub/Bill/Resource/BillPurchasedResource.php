<?php

namespace Orbit\Controller\API\v1\Pub\Bill\Resource;

use Orbit\Helper\MCash\API\Bill;
use Orbit\Helper\Resource\Resource;

/**
 * Order purchased resource class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BillPurchasedResource extends Resource
{
    public function toArray()
    {
        $convenienceFee = $this->getConvenienceFee();

        $this->data = [
            'payment_transaction_id' => $this->payment_transaction_id,
            'external_payment_transaction_id' => $this->external_payment_transaction_id,
            'user_name' => $this->user_name,
            'user_email' => $this->user_email,
            'user_id' => $this->user_id,
            'currency' => $this->currency,
            'amount' => (int) $this->amount,
            'amount_before_fee' => (int) $this->amount - $this->convenienceFee,
            'convenience_fee' => (int) $convenienceFee,
            'payment_status' => $this->status,
            'transaction_time' => $this->created_at->timezone('Asia/Jakarta')
                ->format('Y-m-d H:i:s'),
            'payment_midtrans_info' => $this->midtrans->payment_midtrans_info,
            'bill' => $this->transformBillInformation(),
        ];

        return $this->data;
    }

    private function getConvenienceFee()
    {
        return (int) $this->details->filter(function($detail) {
            return $detail->object_type === 'digital_product';
        })->first()->payload;
    }

    private function transformBillInformation()
    {
        $billType = $this->resource->getBillProductType();

        switch ($billType) {
            case Bill::ELECTRICITY_BILL:
                return $this->transformElectricityBillInformation();
                break;

            default:
                break;
        }
    }

    private function transformElectricityBillInformation()
    {
        $notes = unserialize($this->notes);

        $inquiry = isset($notes['inquiry']) ? $notes['inquiry'] : null;

        return empty($inquiry) ? null : [
            'billing_id' => $inquiry->data->billing_id,
            'customer_name' => $inquiry->data->customer_name,
            'period' => $inquiry->data->period,
            'amount' => $inquiry->amount,
            'admin_fee' => $inquiry->data->admin_fee,
            'total' => $inquiry->total,
            'receipt' => $this->parseElectricityBillReceipt(
                $inquiry->data->receipt->info
            ),
        ];
    }

    private function parseElectricityBillReceipt($receiptInfo)
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
