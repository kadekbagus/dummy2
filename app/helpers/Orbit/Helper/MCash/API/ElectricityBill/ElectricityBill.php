<?php

namespace Orbit\Helper\MCash\API\ElectricityBill;

use Orbit\Helper\MCash\API\Bill;
use Orbit\Helper\MCash\API\ElectricityBill\Inquiry;
use Orbit\Helper\MCash\API\ElectricityBill\Pay;

/**
 * Electricity bill api provider.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ElectricityBill extends Bill
{
    protected $billType = Bill::ELECTRICITY_BILL;

    // Compose feature available for this bill type.
    use Inquiry, Pay;
}
