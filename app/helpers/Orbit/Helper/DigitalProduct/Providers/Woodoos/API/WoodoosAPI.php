<?php namespace Orbit\Helper\DigitalProduct\Providers\Woodoos\API;

use GuzzleHttp\Exception\RequestException;
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

    protected function setBodyParams()
    {
        $this->options['json'] = json_encode($this->buildRequestParam());
    }

    protected function setHeaders()
    {
        $this->options['headers']['Authorization'] = $this->config['passphrase'];
    }

    protected function handleException($e)
    {
        if ($e instanceof RequestException) {
            if ($e->hasResponse()) {
                $response = $e->getResponse()->getBody()->getContents();
                \Log::info('API Exception: ' . $response);
                return $this->response($response);
            }
        }

        return parent::handleException($e);
    }
}
