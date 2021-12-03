<?php

namespace Orbit\Controller\API\v1\Pub\Bill\Resource;

use Orbit\Controller\API\v1\Pub\Bill\Resource\ElectricityBillTransformer;
use Orbit\Controller\API\v1\Pub\Bill\Resource\ISPBillTransformer;
use Orbit\Controller\API\v1\Pub\Bill\Resource\PbbTaxBillTransformer;
use Orbit\Controller\API\v1\Pub\Bill\Resource\WaterBillTransformer;
use Orbit\Helper\MCash\API\Bill;
use Orbit\Helper\Resource\Resource;

/**
 * Order purchased resource class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BillPurchasedResource extends Resource
{
    // Compose which data transformer available for this.
    use ElectricityBillTransformer,
        WaterBillTransformer,
        PbbTaxBillTransformer,
        BpjsBillTransformer,
        ISPBillTransformer;

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
            'amount_before_fee' => (int) $this->amount - $convenienceFee,
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

    private function transformBillInformation($notes)
    {
        $billType = $this->resource->getBillProductType();

        switch ($billType) {
            case Bill::ELECTRICITY_BILL:
                return $this->transformElectricityBillInformation($this->notes);
                break;

            case Bill::PDAM_BILL:
                return $this->transformWaterBillInformation($this->notes);
                break;

            case Bill::PBB_TAX_BILL:
                return $this->transformPbbTaxBillInformation($this->notes);
                break;

            case Bill::BPJS_BILL:
                return $this->transformBpjsBillInformation($this->notes);
                break;

            case Bill::ISP_BILL:
                return $this->transformISPBillInformation($this->notes);
                break;

            default:
                break;
        }

        return null;
    }
}
