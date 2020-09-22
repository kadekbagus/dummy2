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

    // somehow if the Content-Type is set, Upoint cannot read the params
    protected $contentType = null;

    protected function getEnv()
    {
        return Config::get("orbit.digital_product.providers.{$this->providerId}.env", 'sandbox');
    }

    protected function setBodyParams()
    {
        $this->options['form_params'] = $this->buildRequestParam();
    }
}
