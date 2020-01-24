<?php namespace Orbit\Helper\DigitalProduct\Providers\Ayopay\API;

use Orbit\Helper\DigitalProduct\API\BaseAPI;

/**
 * Base AyoPay API...
 *
 * @author Budi <budi@gotomalls.com>
 */
class AyoPayAPI extends BaseAPI
{
    protected $method = 'POST';

    protected $providerId = 'ayopay';

    protected $endPoint = 'h2h/voucher.aspx';

    protected $contentType = 'text/xml; charset=UTF-8';
}
