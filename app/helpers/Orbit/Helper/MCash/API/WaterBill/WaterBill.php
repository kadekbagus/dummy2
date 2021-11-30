<?php

namespace Orbit\Helper\MCash\API\WaterBill;

use Orbit\Helper\MCash\API\Bill;
use Orbit\Helper\MCash\API\WaterBill\Inquiry;
use Orbit\Helper\MCash\API\WaterBill\Pay;

/**
 * Water bill api provider.
 *
 * @author Budi <budi@gotomalls.com>
 */
class WaterBill extends Bill
{
    protected $billType = Bill::PDAM_BILL;

    // Compose feature available for this bill type.
    use Inquiry, Pay;
}
