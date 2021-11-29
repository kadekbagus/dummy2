<?php

namespace Orbit\Helper\MCash\API\BPJSBill;

use Orbit\Helper\MCash\API\Bill;
use Orbit\Helper\MCash\API\BPJSBill\Inquiry;
use Orbit\Helper\MCash\API\BPJSBill\Pay;

/**
 * BPJS bill api provider.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BPJSBill extends Bill
{
    protected $billType = Bill::BPJS_BILL;

    // Compose feature available for this bill type.
    use Inquiry, Pay;
}
