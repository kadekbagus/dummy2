<?php namespace Orbit\Helper\DigitalProduct\Providers\Woodoos\API;

use Orbit\Helper\DigitalProduct\API\BaseAPI;

/**
 * Base Woodoos API...
 *
 * @author Budi <budi@gotomalls.com>
 */
class WoodoosAPI extends BaseAPI
{
    protected $method = 'POST';

    protected $providerId = 'woodoos';

    /**
     * Set to null so it wont be included in the header.
     */
    protected $contentType = null;

    protected function setBodyParams()
    {
        $this->options['form_params'] = $this->buildRequestParam();
    }
}
