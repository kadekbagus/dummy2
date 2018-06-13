<?php namespace Orbit\Helper\Midtrans\API\Response;

use Orbit\Helper\Midtrans\API\Response\Response as MidtransResponse;

/**
 * Transaction Status Response mapper for Midtrans API.
 * 
 */
class TransactionStatusResponse
{
    protected $code = 500;

    protected $message = 'Internal Server Error.';

    protected $data = null;

    function __construct($response)
    {
        if (empty($response)) {
            throw new Exception('Empty response when creating TransactionStatusResponse.');
        }

        $this->data = $response;
    }

    /**
     * Determine if Transaction status is success or not.
     *
     * See https://api-docs.midtrans.com/#best-practice-to-handle-notification
     * 
     * @return boolean [description]
     */
    public function isSuccess()
    {
        return $this->data->status_code === MidtransResponse::STATUS_SUCCESS &&
                strtolower($this->data->fraud_status) === 'accept' &&
                in_array(strtolower($this->data->transaction_status), ['settlement', 'capture']);
    }

    /**
     * Determine if transaction is denied by bank/Midtrans.
     * 
     * @return boolean [description]
     */
    public function isDenied()
    {
        return $this->data->status_code === MidtransResponse::STATUS_DENIED;
    }

    /**
     * Determine if the transaction is pending or not.
     * Pending transaction will be expired in 2 hours.
     * 
     * See https://api-docs.midtrans.com/#code-2xx
     * 
     * @return boolean [description]
     */
    public function isPending()
    {
        return $this->data->status_code === MidtransResponse::STATUS_PENDING;
    }

    /**
     * Determine if transaction is expired.
     * 
     * @return boolean [description]
     */
    public function isExpired()
    {
        return $this->data->status_code === MidtransResponse::STATUS_EXPIRED;
    }

    /**
     * Get transaction status_code.
     * 
     * @return [type] [description]
     */
    public function getCode()
    {
        return $this->data->status_code;
    }

    /**
     * Get transaction status_message.
     * 
     * @return [type] [description]
     */
    public function getMessage()
    {
        return $this->data->status_message;
    }

    /**
     * Get the real response from Midtrans.
     * 
     * @return [type] [description]
     */
    public function getData()
    {
        return $this->data;
    }
}
