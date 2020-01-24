<?php namespace Orbit\Helper\DigitalProduct\Response;

use OrbitShop\API\v1\ResponseProvider;

/**
 * basic 3rd party API response mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BaseResponse
{
    protected $response = null;

    protected $maxRetry = 3;

    function __construct($response = null)
    {
        $this->response = new ResponseProvider;

        if (is_object($response)) {
            $this->response->setData($response);
        }

        if (is_string($response)) {
            $this->response->setMessage($response);
        }
    }

    public function setStatus($status)
    {
        $this->response->status = $status;
    }

    public function isSuccess()
    {
        return $this->response->status === 'success';
    }

    public function isPending()
    {
        return $this->response->status === 'pending';
    }

    public function getData()
    {
        return $this->response->data;
    }

    public function getFailureMessage()
    {
        return $this->response->message;
    }

    public function __toString()
    {
        return $this->response->message;
    }

    public function __get($key)
    {
        return isset($this->response->data->{$key})
            ? $this->response->data->{$key}
            : null;
    }
}
