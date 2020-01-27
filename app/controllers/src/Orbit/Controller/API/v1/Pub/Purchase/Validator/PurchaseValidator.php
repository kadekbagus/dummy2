<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Validator;

use App;
use PaymentTransaction;

/**
 * Validator related to purchase.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseValidator
{
    public function exists($attribute, $purchaseId, $parameters)
    {
        $purchase = PaymentTransaction::with(['details'])->where('payment_transaction_id', $purchaseId)->first();

        App::instance('purchase', $purchase);

        return ! empty($purchase);
    }
}
