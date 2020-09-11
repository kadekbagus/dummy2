<?php namespace Orbit\Helper\DigitalProduct\Providers\UPoint\API;

use Config;
use Orbit\Helper\DigitalProduct\API\BaseAPI;

/**
 * Base AyoPay API...
 *
 * @author Budi <budi@gotomalls.com>
 */
class UPointAPI extends BaseAPI
{
    protected $method = 'POST';

    protected $providerId = 'upoint';

    protected $contentType = 'application/x-www-form-urlencode';

    protected function getEnv()
    {
        return Config::get("orbit.digital_product.providers.{$this->providerId}.env", 'sandbox');
    }
}
