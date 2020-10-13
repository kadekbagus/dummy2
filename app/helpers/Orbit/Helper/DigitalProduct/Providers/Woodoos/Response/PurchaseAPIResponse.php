<?php namespace Orbit\Helper\DigitalProduct\Providers\Woodoos\Response;

use Orbit\Helper\DigitalProduct\Providers\Woodoos\Response\WoodoosResponse;
use Orbit\Helper\DigitalProduct\Response\HasVoucherData;

/**
 * Woodoos Purchase API Response mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseAPIResponse extends WoodoosResponse
{
    use HasVoucherData;

    public function __construct($response)
    {
        parent::__construct($response);

        $this->parseVoucherData($this->response->data);
    }

    public function getRefNumber()
    {
        return isset($this->response->data->referenceNumber)
            ? $this->response->data->referenceNumber
            : null;
    }

    protected function parseVoucherData($responseData)
    {
        if (isset($responseData->cardNumber)) {
            $this->voucherData['Token'] = wordwrap($responseData->cardNumber, 4, '-', true);
        }
    }
}
