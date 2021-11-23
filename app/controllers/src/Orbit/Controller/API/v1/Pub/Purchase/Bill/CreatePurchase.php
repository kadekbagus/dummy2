<?php

namespace Orbit\Controller\API\v1\Pub\Purchase\Bill;

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
    protected $objectType = 'digital_product';

    protected function applyPromoCode()
    {
        // do nothing now.
    }

    protected function beforeCommitHooks()
    {

    }
}
