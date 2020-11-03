<?php namespace Orbit\Helper\DigitalProduct\Providers\Woodoos\Response;

use Orbit\Helper\DigitalProduct\Response\BaseResponse;
use Orbit\Helper\DigitalProduct\Response\HasVoucherData;

/**
 * Woodoos base Response mapper.
 *
 * @author Budi <budi@gotomalls.com>
 */
class WoodoosResponse extends BaseResponse
{
    use HasVoucherData;

    /**
     * Accept well-formatted xml string...
     *
     * @param [type] $response [description]
     */
    public function __construct($response)
    {
        $this->rawResponse = $response;

        if (is_string($response)) {
            $response = json_decode($response);
        }

        parent::__construct($response);

        $this->parseVoucherData($this->response->data);
    }

    protected function parseVoucherData($responseData)
    {
        if (isset($responseData->cardNumber) && ! empty($responseData->cardNumber)) {
            $this->voucherData['Token'] = wordwrap($responseData->cardNumber, 4, '-', true);
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
            && isset($this->response->data->isSuccessful)
            && ($this->response->data->isSuccessful === true
                || $this->response->data->isSuccessful === 'true');
    }

    public function isSuccessWithToken()
    {
        return $this->isSuccess() && ! empty($this->voucherData);
    }

    public function getFailureMessage()
    {
        return isset($this->response->data->errorMessage)
            ? $this->response->data->errorMessage
            : 'Unknown error!';
    }
}
