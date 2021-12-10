<?php

namespace Orbit\Notifications\Traits;

use Orbit\Helper\MCash\API\Bill;
use Orbit\Notifications\Traits\CommonHelper;
use Orbit\Notifications\Traits\HasContactTrait;
use Orbit\Notifications\Traits\HasPaymentTrait;
use PaymentTransaction;

/**
 * Common method helpers related to notification for Bill transaction.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait HasBillTrait
{
    // Compose available functions for bill related email notifications
    use CommonHelper, HasPaymentTrait, HasContactTrait;

    /**
     * Get the payment object instance based on given transaction id.
     *
     * @param  string $paymentId - payment transaction id
     * @return PaymentTransaction - payment transaction object instance.
     */
    protected function getPayment($paymentId)
    {
        return PaymentTransaction::onWriteConnection()->with([
                'details.digital_product',
                'details.provider_product',
                'midtrans',
                'user',
                'discount',
            ])->findOrFail($paymentId);
    }

    protected function getConvenienceFee()
    {
        return $this->payment->details->filter(function($detail) {
            return $detail->object_type === 'digital_product';
        })->first()->payload;
    }

    /**
     * Get bill information.
     * At the moment, we parse payment response. Might be overriden if need to
     * parse from payment response.
     *
     * @return [type] [description]
     */
    protected function getBillInformation($source = 'payment')
    {
        $notes = unserialize($this->payment->notes);

        if (! isset($notes[$source])) {
            return null;
        }

        $billInfo = $notes[$source];

        $source = ucfirst($source);
        $response = 'Orbit\Helper\MCash\API\%s\Response\\'
            . $source . 'Response';

        switch ($this->payment->getBillProductType()) {
            case Bill::ELECTRICITY_BILL:
                $response = sprintf($response, 'ElectricityBill');
                break;

            case Bill::PDAM_BILL:
                $response = sprintf($response, 'WaterBill');
                break;

            case Bill::PBB_TAX_BILL:
                $response = sprintf($response, 'PBBTaxBill');
                break;

            case Bill::BPJS_BILL:
                $response = sprintf($response, 'BPJSBill');
                break;

            case Bill::ISP_BILL:
                $response = sprintf($response, 'InternetProviderBill');
                break;

            default:
                return null;
                break;
        }

        return (new $response($billInfo))->getBillInformation();
    }
}
