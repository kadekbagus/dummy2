<?php

namespace Orbit\Helper\MCash\API\InternetProviderBill;

use Orbit\Helper\MCash\API\Bill;
use Orbit\Helper\MCash\API\InternetProviderBill\Inquiry;
use Orbit\Helper\MCash\API\InternetProviderBill\Pay;

/**
 * ISP bill api provider.
 *
 * @author Budi <budi@gotomalls.com>
 */
class InternetProviderBill extends Bill
{
    protected $billType = Bill::ISP_BILL;

    // Compose feature available for this bill type.
    use Inquiry, Pay;
}
