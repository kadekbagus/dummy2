<?php namespace Orbit\Helper\DigitalProduct\Providers\Woodoos\API;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Config;
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

    protected function getEnv()
    {
        return Config::get("orbit.digital_product.providers.{$this->providerId}.env", 'sandbox');
    }

    protected function setBodyParams()
    {
        $this->options['json'] = $this->buildRequestParam();
    }

    protected function setHeaders()
    {
        $this->options['auth'] = [
            $this->config['merchant_id'],
            $this->config['passphrase'],
        ];
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
