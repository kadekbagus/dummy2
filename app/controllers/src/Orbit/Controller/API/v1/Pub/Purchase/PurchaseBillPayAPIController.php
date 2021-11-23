<?php

namespace Orbit\Controller\API\v1\Pub\Purchase;

use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Bill\Repository\BillRepository;
use Orbit\Controller\API\v1\Pub\Purchase\Bill\CreatePurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Request\Bill\BillInquiryRequest;
use Orbit\Controller\API\v1\Pub\Purchase\Request\Bill\CreateBillPurchaseRequest;

/**
 * Handle request pay the bill.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseBillPayAPIController extends PubControllerAPI
{
    function handle(
        // CreateBillPurchaseRequest $purchaseRequest,
        BillPayRequest $request,
        BillRepository $bill
    ) {
        try {
            // @todo make sure to pass the proper validation rules
            //       for digital product/whatever the type is.
            // $purchase = (new CreatePurchase())->create($request);

            $this->response->data = $bill->pay(
                $request->bill_type,
                [
                    'product' => $purchase->getProductType(),
                    'customer' => $request->bill_id,
                    'partnerTrxid' => $purchase->payment_transaction_id,
                    'amount' => $purchase->amount,
                ]
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
