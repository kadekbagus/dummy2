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
    // Compose feature available for this bill type.
    use Inquiry, Pay;

    protected function mockResponse($data = [])
    {
        $this->mockData = (object) array_merge([
                'status' => 0,
                'message' => 'TRX SUCCESS',
                'data' => (object) [
                    'serial_number' => '12313131',
                ]
            ], $data);

        return $this;
    }
}
