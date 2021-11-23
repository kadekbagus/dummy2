<?php

namespace Orbit\Controller\API\v1\Pub\Purchase\Request\Bill;

use Orbit\Controller\API\v1\Pub\Purchase\Request\Bill\BillPurchaseRequest;

/**
 * New pln bill purchase request validator.
 *
 * @author Budi <budi@gotomalls.com>
 */
class NewPlnBillPurchaseRequest extends BillPurchaseRequest
{
    protected $billType = 'pln';
}
