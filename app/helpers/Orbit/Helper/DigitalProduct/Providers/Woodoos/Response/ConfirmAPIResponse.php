<?php namespace Orbit\Helper\DigitalProduct\Providers\Woodoos\Response;

use Orbit\Helper\DigitalProduct\Providers\Woodoos\Response\WoodoosResponse;
use Orbit\Helper\DigitalProduct\Response\HasVoucherData;

/**
 * Woodoos Purchase API Response mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ConfirmAPIResponse extends WoodoosResponse
{
    use HasVoucherData;

    public function __construct($response)
    {
        parent::__construct($response);

        $this->parseVoucherData($this->response->data);
    }
}
