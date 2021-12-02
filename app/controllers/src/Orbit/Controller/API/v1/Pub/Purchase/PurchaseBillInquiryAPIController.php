<?php

namespace Orbit\Controller\API\v1\Pub\Purchase;

use Exception;
use Illuminate\Support\Facades\App;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Purchase\Bill\CreatePurchase;
use Orbit\Controller\API\v1\Pub\Purchase\Request\Bill\BillInquiryRequest;
use Orbit\Helper\MCash\API\BillInterface;

/**
 * Handle request to fetch bill detail information (inquiry)
 * (like pln info kwh/bill amount etc.)
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseBillInquiryAPIController extends PubControllerAPI
{
    public function handle(BillInquiryRequest $request, BillInterface $bill)
    {
        try {
            // Create a new purchase before sending inquiry request,
            // because we need our internal trxID to be sent as param
            // (partner_trxid).
            $purchase = (new CreatePurchase())
                ->onBeforeCommit(function($purchase, $request) use ($bill) {
                    // So, after creating the purchase, we do inquiry request
                    // to mcash and once we get the billing information,
                    // we update our purchase information (amount, price, etc2).

                    // @todo consider moving inquiry process to
                    //       beforeCommitHooks inside createPurchase helper
                    //       instead of setting callback here (looks ugly).

                    $providerProduct = App::make('providerProduct');

                    $inquiry = $bill->inquiry([
                        'product' => $providerProduct->code,
                        'customer' => $request->bill_id,
                        'partnerTrxId' => $purchase->payment_transaction_id,
                    ]);

                    if (! $inquiry->isSuccess()) {
                        throw new Exception('inquiry failed! '
                            . $inquiry->getMessage()
                        );
                    }

                    // Calculate convenience fee...
                    $billInfo = $inquiry->getBillInformation();
                    list($mdr, $profitPercentage, $convenienceFee) = $purchase
                        ->calculateConvenienceFee($purchase, $billInfo);

                    // ...and adjust purchase amount and parameters accordingly
                    // (mdr, profit, fee etc).
                    $purchase->adjustPurchaseAmount(
                        $purchase,
                        $inquiry,
                        $mdr,
                        $profitPercentage,
                        $convenienceFee
                    );

                    // Add inquiry/billing information into the response,
                    // so that frontend can create token with the right purchase
                    // amount.
                    $billInfo->convenience_fee = $convenienceFee;
                    $purchase->bill = $billInfo;
                })
                ->create($request);

            unset($purchase->notes);

            $this->response->data = $purchase;

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
