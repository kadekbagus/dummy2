<?php namespace Orbit\Helper\DigitalProduct\Providers\UPoint\Response;

use Orbit\Helper\DigitalProduct\Response\BaseResponse;

/**
 * AyoPay base Response mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class UPointResponse extends BaseResponse
{
    protected $voucherData = [];

    /**
     * Accept well-formatted xml string...
     *
     * @param [type] $response [description]
     */
    public function __construct($response)
    {
        $this->rawResponse = $response;

        parent::__construct($response);

        if (isset($this->response->data->status_msg)) {
            $this->response->setMessage($this->response->data->status_msg);
        }
    }

    /**
     * Determine that the response is success.
     *
     * @return boolean [description]
     */
    public function isSuccess()
    {
        return null !== $this->response->data
            && (isset($this->response->data->status)
            && 1 === (int) $this->response->data->status);
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
     * Just return the raw response from API.
     *
     * @return [type] [description]
     */
    public function getData()
    {
        return $this->rawResponse;
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
