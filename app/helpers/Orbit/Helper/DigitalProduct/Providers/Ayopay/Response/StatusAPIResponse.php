<?php namespace Orbit\Helper\DigitalProduct\Providers\Ayopay\Response;

use Orbit\Helper\DigitalProduct\Providers\Ayopay\Response\AyoPayResponse;

/**
 * AyoPay Purchase API Response mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class StatusAPIResponse extends AyoPayResponse
{
    /**
     * Determine that the response is success.
     *
     * @return boolean [description]
     */
    public function isSuccess()
    {
        return null !== $this->response->data
            && (isset($this->response->data->status) && 100 === (int) $this->response->data->status);
    }

    /**
     * Determine that the purchase is still pending.
     *
     * @return boolean [description]
     */
    public function isPending()
    {
        return ! empty($this->response->data)
            && (isset($this->response->data->status) && 100 === (int) $this->response->data->status);
    }
}
