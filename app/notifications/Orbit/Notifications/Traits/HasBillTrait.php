<?php

namespace Orbit\Notifications\Traits;

use Orbit\Helper\MCash\API\BPJSBill\Response\InquiryResponse as BPJSBillInquiryResponse;
use Orbit\Helper\MCash\API\Bill;
use Orbit\Helper\MCash\API\ElectricityBill\Response\InquiryResponse as ElectricityInquiryResponse;
use Orbit\Helper\MCash\API\InternetProviderBill\Response\InquiryResponse as ISPBillInquiryResponse;
use Orbit\Helper\MCash\API\PBBTaxBill\Response\InquiryResponse as PBBTaxBillInquiryResponse;
use Orbit\Helper\MCash\API\WaterBill\Response\InquiryResponse as WaterBillInquiryResponse;
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

    /**
     * Get bill information.
     * At the moment, we parse inquiry response. Might be overriden if need to
     * parse from payment response.
     *
     * @return [type] [description]
     */
    protected function getBillInformation($source = 'inquiry')
    {
        $notes = unserialize($this->payment->notes);

        if (! isset($notes[$source])) {
            return null;
        }

        $billInfo = $notes[$source];

        switch ($this->payment->getBillProductType()) {
            case Bill::ELECTRICITY_BILL:
                $billInfo = new ElectricityInquiryResponse($billInfo);
                break;

            case Bill::PDAM_BILL:
                $billInfo = new WaterBillInquiryResponse($billInfo);
                break;

            case Bill::PBB_TAX_BILL:
                $billInfo = new PBBTaxBillInquiryResponse($billInfo);
                break;

            case Bill::BPJS_BILL:
                $billInfo = new BPJSBillInquiryResponse($billInfo);
                break;

            case Bill::ISP_BILL:
                $billInfo = new ISPBillInquiryResponse($billInfo);
                break;

            default:
                return null;
                break;
        }

        return $billInfo->getBillInformation();
    }
}
