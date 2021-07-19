<?php

namespace Orbit\Controller\API\v1\Pub\Purchase\Order;

use Orbit\Controller\API\v1\Pub\Purchase\BaseCreatePurchase;
use Order;
use PaymentTransactionDetail;
use Request;

/**
 * Brand Product Order Purchase
 *
 * @author Budi <budi@gotomalls.com>
 */
class CreatePurchase extends BaseCreatePurchase
{
    protected $objectType = 'order';

    protected function initItem()
    {
        $this->item = Order::createFromRequest($this->request);
    }

    protected function getTotalAmount()
    {
        return array_reduce($this->item, function($total, $item) {
            return $total + $item->total_amount;
        }, 0);
    }

    protected function createPaymentTransactionDetail()
    {
        foreach($this->item as $item) {
            $this->purchaseDetail = PaymentTransactionDetail::create([
                'payment_transaction_id' => $this->purchase->payment_transaction_id,
                'currency' => $this->request->currency,
                'price' => $item->total_amount,
                'quantity' => 1,
                'vendor_price' => $item->total_amount,
                'object_id' => $item->order_id,
                'object_type' => $this->request->object_type,
                'object_name' => "Product Order {$item->order_id}",
                'payload' => $item->cart_item_ids,
            ]);
        }
    }

    protected function applyPromoCode()
    {
        // do nothing now.
    }
}
