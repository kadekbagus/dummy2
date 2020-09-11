<?php namespace Orbit\Helper\DigitalProduct\Providers\UPoint\Response\DTU;

use Orbit\Helper\DigitalProduct\Providers\UPoint\Response\UPointResponse;

/**
 * AyoPay Purchase API Response mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseAPIResponse extends UPointResponse
{
    /**
     * Determine that the response is success.
     *
     * @return boolean [description]
     */
    public function isSuccess()
    {
        return null !== $this->response->data
            && (isset($this->response->data->status)
            && 100 === (int) $this->response->data->status);
    }
}
