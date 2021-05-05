<?php namespace Orbit\Helper\Midtrans\API\Response;

use Orbit\Helper\Midtrans\API\Response\Response as MidtransResponse;

use PaymentTransaction;
use Config;

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
        $fraudStatus = isset($this->data->fraud_status) ? $this->data->fraud_status : 'accept';
        return $this->data->status_code === MidtransResponse::STATUS_SUCCESS &&
                strtolower($fraudStatus) === 'accept' &&
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
        return $this->data->status_code === MidtransResponse::STATUS_PENDING || $this->data->transaction_status === 'pending';
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
     * Determine if the transaction is suspicious.
     *
     * @return boolean [description]
     */
    public function isSuspicious()
    {
        $fraudStatus = isset($this->data->fraud_status) ? $this->data->fraud_status : 'challenge';
        return $this->data->transaction_status === 'capture' &&
               strtolower($fraudStatus) === 'challenge' &&
               $this->data->status_code === MidtransResponse::STATUS_PENDING;
    }

    /**
     * Determine if the transaction is canceled.
     *
     * @return boolean [description]
     */
    public function isCanceled()
    {
        return $this->data->transaction_status === 'cancel';
    }

    /**
     * Determine if midtrans trx is being refunded.
     *
     * @return [type] [description]
     */
    public function wasRefunded()
    {
        return $this->hasRefund() || in_array($this->data->transaction_status, ['refund', 'partial_refund']);
    }

    /**
     * Determine if midtrans trx has refund property in the response.
     *
     * @return boolean [description]
     */
    public function hasRefund()
    {
        return isset($this->data->refund_amount) && isset($this->data->refunds);
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
     * Decide if we should retry or not based on status_code from Midtrans.
     *
     * @param  integer $code [description]
     * @return [type]        [description]
     */
    public function shouldRetryChecking($checkTimes = 0)
    {
        return ($this->isPending() || in_array($this->getCode(), [500, 502, 503, 505])) &&
               $checkTimes < Config::get('orbit.partners_api.midtrans.transaction_status_max_retry', 60);
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

    /**
     * Determine if current transaction was paid by specific payment method(s).
     *
     * @return bool
     */
    public function paidWith($method = [])
    {
        if (! empty($this->data)
            && isset($this->data->payment_type)
            && ! empty($method)
        ) {
            return in_array($this->data->payment_type, $method);
        }

        return false;
    }

    public function paidWithShopee()
    {
        return $this->paidWith(['shopeepay'])
            || (! empty($this->data)
                && isset($this->data->acquirer)
                && stripos($this->data->acquirer, 'shopee') >= 0);
    }

    /**
     * Map midtrans response to our internal payment status.
     * This method MUST BE synced to Frontend transaction status/midtrans notification response mapper.
     *
     * @return [type] [description]
     */
    public function mapToInternalStatus()
    {
        if ($this->isSuccess()) {
            return PaymentTransaction::STATUS_SUCCESS;
        }
        else if ($this->isCanceled()) {
            return PaymentTransaction::STATUS_CANCELED;
        }
        else if ($this->isExpired()) {
            return PaymentTransaction::STATUS_EXPIRED;
        }
        else if ($this->isDenied()) {
            return PaymentTransaction::STATUS_DENIED;
        }
        else if ($this->isPending()) {
            return PaymentTransaction::STATUS_PENDING;
        }
        else if ($this->isSuspicious()) {
            return PaymentTransaction::STATUS_PENDING;
        }
        else if ($this->wasRefunded()) {
            return PaymentTransaction::STATUS_REFUND;
        }

        return PaymentTransaction::STATUS_FAILED;
    }
}
