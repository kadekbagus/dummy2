<?php namespace Orbit\Helper\DigitalProduct\Providers\Woodoos\Response;

use Orbit\Helper\DigitalProduct\Providers\Woodoos\Response\WoodoosResponse;

/**
 * Woodoos Purchase API Response mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseAPIResponse extends WoodoosResponse
{
    public function getRefNumber()
    {
        return isset($this->response->data->referenceNumber)
            ? $this->response->data->referenceNumber
            : null;
    }

    public function isSuccessWithoutToken()
    {
        return $this->isSuccess() && empty($this->voucherData);
    }
}
