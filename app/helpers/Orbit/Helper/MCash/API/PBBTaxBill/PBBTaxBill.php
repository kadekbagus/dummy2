<?php

namespace Orbit\Helper\MCash\API\PBBTaxBill;

use Orbit\Helper\MCash\API\Bill;
use Orbit\Helper\MCash\API\PBBTaxBill\Inquiry;
use Orbit\Helper\MCash\API\PBBTaxBill\Pay;

/**
 * PBB Tax bill api provider.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PBBTaxBill extends Bill
{
    protected $billType = Bill::PBB_TAX_BILL;

    // Compose feature available for this bill type.
    use Inquiry, Pay;
}
