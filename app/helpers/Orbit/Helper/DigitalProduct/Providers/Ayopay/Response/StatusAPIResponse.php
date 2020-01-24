<?php namespace Orbit\Helper\DigitalProduct\Providers\Ayopay\Response;

use Orbit\Helper\DigitalProduct\Response\BaseResponse;

/**
 * AyoPay Purchase API Response mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class StatusAPIResponse extends BaseResponse
{
    /**
     * Accept well-formatted xml string...
     *
     * @param [type] $response [description]
     */
    public function __construct($response)
    {
        if (is_string($response)) {
            $xmlResponse = simplexml_load_string($response);

            if (is_object($xmlResponse)) {
                $response = $xmlResponse;
            }
        }

        parent::__construct($response);
    }

    /**
     * Determine that the response is success.
     *
     * @return boolean [description]
     */
    public function isSuccess()
    {
        return ! empty($this->response->data)
            && (isset($this->response->data->status) && 100 === (int) $this->response->data->status)
            && (isset($this->response->data->message) && strtolower($this->response->data->message) === 'sukses');
    }

    /**
     * Determine that the purchase is still pending.
     *
     * @return boolean [description]
     */
    public function isPending()
    {
        return ! empty($this->response->data)
            && 100 === (int) $this->response->data->status;
    }

    /**
     * Don't support retry at the moment.
     *
     * @param  integer $retry [description]
     * @return [type]         [description]
     */
    public function shouldRetry($retry = 1)
    {
        return false;
    }

    /**
     * Get the failure message from AyoPay.
     *
     * @return [type] [description]
     */
    public function getFailureMessage()
    {
        return $this->response->message;
    }
}
