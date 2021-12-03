<?php

namespace Orbit\Controller\API\v1\Pub\Bill;

use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Bill\Request\BillPurchasedDetailRequest;
use Orbit\Controller\API\v1\Pub\Bill\Resource\BillPurchasedResource;
use PaymentTransaction;

/**
 * Bill purchased detail handler.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BillPurchasedDetailAPIController extends PubControllerAPI
{
    public function handle(BillPurchasedDetailRequest $request)
    {
        try {

            $this->response->data = new BillPurchasedResource(
                PaymentTransaction::with([
                        'details.digital_product',
                        'details.provider_product',
                        'midtrans',
                    ])
                    ->where('payment_transaction_id', $request->payment_transaction_id)
                    ->orWhere('external_payment_transaction_id', $request->payment_transaction_id)
                    ->firstOrFail()
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
