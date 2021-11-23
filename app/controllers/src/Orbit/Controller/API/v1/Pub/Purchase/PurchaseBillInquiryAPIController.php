<?php

namespace Orbit\Controller\API\v1\Pub\Purchase;

use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Bill\Repository\BillRepository;
use Orbit\Controller\API\v1\Pub\Purchase\Bill\CreatePurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Request\Bill\BillInquiryRequest;
use Orbit\Controller\API\v1\Pub\Purchase\Request\Bill\CreateBillPurchaseRequest;

/**
 * Handle request to fetch bill detail information (inquiry)
 * (like pln info kwh/bill amount etc.)
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseBillInquiryAPIController extends PubControllerAPI
{
    function handle(
        // CreateBillPurchaseRequest $purchaseRequest,
        BillInquiryRequest $request,
        BillRepository $bill
    ) {
        try {
            // Create a new purchase before sending inquiry request,
            // because we need our internal trxID to be sent as param
            // (partner_trxid).
            //
            // @todo make sure to pass the proper validation rules
            //       for digital product/whatever the type is.
            $purchase = (new CreatePurchase())->create($request);

            // Request inquiry
            $inquiry = $bill->inquiry([
                'product' => $purchase->getBillProductId(),
                'customer' => $request->bill_id,
                'partnerTrxId' => $purchase->payment_transaction_id,
            ]);

            // Update/adjust total amount and other things based on the
            // inquiry response.
            $purchase->updateBillAmount($inquiry);

            $this->response->data = $purchase;

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }
}
