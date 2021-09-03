<?php namespace Orbit\Helper\Midtrans\API\Response;

use Orbit\Helper\Midtrans\API\Response\Response as MidtransResponse;

use PaymentTransaction;
use Config;

/**
 * Direct refund Response mapper for Midtrans API.
 *
 * @see https://api-docs.midtrans.com/#direct-refund-transaction
 *
 * @author Budi <budi@gotomalls.com>
 */
class DirectRefundResponse
{
    protected $code = 500;

    protected $message = 'Internal Server Error.';

    protected $data = null;

    function __construct($response)
    {
        if (empty($response)) {
            throw new Exception('Empty response.');
        }

        $this->data = $response;
    }

    /**
     * Determine if Refund is success or not.
     *
     * @see https://api-docs.midtrans.com/#best-practice-to-handle-notification
     *
     * @return boolean [description]
     */
    public function isSuccess()
    {
        return $this->data->status_code === MidtransResponse::STATUS_SUCCESS
                && in_array(strtolower($this->data->transaction_status), [
                        'refund', 'partial_refund'
                    ]);
    }

    /**
     * Determine if refund was rejected.
     *
     * @return [type] [description]
     */
    public function wasRejected()
    {
        return $this->data->status_code === MidtransResponse::STATUS_DENIED;
    }

    /**
     * Determine if midtrans trx has refund property in the response.
     *
     * @return boolean [description]
     */
    public function hasRefund()
    {
        return isset($this->data->refund_key);
    }

    /**
     * Determine if the the transaction is exists or not.
     *
     * @return boolean [description]
     */
    public function notFound()
    {
        // Use loose/not strict comparison...
        return $this->data->status_code == MidtransResponse::STATUS_NOT_FOUND;
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

    public function getStatus()
    {
        return $this->isSuccess() ? 'Success' : 'Failed';
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
