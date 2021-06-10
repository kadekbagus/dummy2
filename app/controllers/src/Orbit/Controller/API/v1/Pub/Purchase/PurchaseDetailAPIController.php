<?php namespace Orbit\Controller\API\v1\Pub\Purchase;

use App;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Purchase\Request\PurchaseDetailRequest;
use PaymentTransaction;

/**
 * Handle purchase detail request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseDetailAPIController extends PubControllerAPI
{
    public function getDetail()
    {
        $httpCode = 200;

        try {
            // $this->enableQueryLog();

            with($request = new PurchaseDetailRequest($this));

            $purchaseId = $request->payment_transaction_id;
            $bypassUser = $request->bypass_user ?: 'N';
            $purchase = PaymentTransaction::select(
                'payment_transaction_id',
                'external_payment_transaction_id',
                'extra_data',
                'amount',
                'status',
                'payment_method',
                'currency',
                'user_email'
            );

            // Don't check for related User unless front-end explicitly requesting it.
            // Useful for request like Midtrans' payment notification which doesn't have any
            // user information/session available when doing the request.
            if ($bypassUser === 'N') {
                $purchase->where('user_id', $request->user()->user_id);
            }

            // payment_transaction_id is value of payment_transaction_id or external_payment_transaction_id
            $purchase = $purchase->where(function($query) use($purchaseId) {
                            $query->where('payment_transactions.payment_transaction_id', '=', $purchaseId)
                                  ->orWhere('payment_transactions.external_payment_transaction_id', '=', $purchaseId);
                        })->first();

            if (empty($purchase)) {
                $httpCode = 404;
                $this->response->data = null;
                $this->response->code = 404;
                $this->response->status = 'error';
                $this->response->message = 'Transaction not found';
            } else {
                $this->response->data = $purchase;
                $this->response->code = 0;
                $this->response->status = 'success';
                $this->response->message = 'Request OK';
            }

            // Record activity...
            // App::make('currentUser')->activity(new TransactionStartingActivity($purchaseRepository->getPurchase()));

        } catch(Exception $e) {
            return $this->handleException($e);
        }

        return $this->render($httpCode);
    }
}
