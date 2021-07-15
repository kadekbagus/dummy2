<?php

namespace Orbit\Controller\API\v1\Pub\Purchase\Order;

use Orbit\Controller\API\v1\Pub\Purchase\BaseCreatePurchase;
use Order;
use Request;

/**
 * Brand Product Order Purchase
 *
 * @todo Create a proper base purchase creator/updater.
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

    protected function buildPurchaseDetailData()
    {
        return array_merge(parent::buildPurchaseDetailData(), [
            'object_id' => $this->item->order_id,
            'object_name' => "Product Order {$this->item->order_id}",
        ]);
    }

    protected function applyPromoCode()
    {
        // do nothing now.
    }
}
