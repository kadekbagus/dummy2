<?php

namespace Orbit\Helper\MCash\API\Responses;

/**
 * Purchase response...
 */
class PurchaseResponse
{
    protected $code = 500;

    protected $message = 'Failed';

    protected $data = null;

    /**
     * List of status considered as retry-able.
     *
     * @var [type]
     */
    protected $retryStatus = [609, 413];

    function __construct($object = null)
    {
        if (is_object($object)) {
            $this->data = $object;
            $this->message = $object->message;
        }
        else {
            $this->message = $object;
        }
    }

    /**
     * Determine if purchase is success or not.
     *
     * @return boolean [description]
     */
    public function isSuccess()
    {
        return ! empty($this->data) && $this->data->status === 0 && ! isset($this->data->pending);
    }

    /**
     * Determine if mcash purchase is Pending.
     * Since Mcash don't explain clearly about flag/indicator of pending transaction,
     * the only thing we can do is by guessing their API response.
     *
     * At the moment, from their API response, we will:
     *     - check if status is 0
     *     - and check if property 'pending' is exist
     *
     * @return boolean [description]
     */
    public function isPending()
    {
        return ! empty($this->data) && $this->data->status === 0 && isset($this->data->pending);
    }

    public function isNotAvailable()
    {
        return ! empty($this->data) && $this->data->status === 413;
    }

    /**
     * Determine if we should retry the purchase when we get specific
     * response code from MCash.
     *
     * @param  [type] $retry [description]
     * @return [type]        [description]
     */
    public function shouldRetry($retry = 1, $maxRetry = 30)
    {
        return ! empty($this->data)
               && in_array($this->data->status, $this->retryStatus)
               && $retry < $maxRetry;
    }

    /**
     * Determine if the take voucher request failed because of the voucher is expired.
     *
     * @todo  should use a better approach to determine expired status (maybe ask Sepulsa to provide a specific code/etc)
     * @return boolean [description]
     */
    public function isExpired()
    {
        return stripos($this->message, 'expired') !== false;
    }

    /**
     * Get purchase response status.
     *
     * @return [type] [description]
     */
    public function getCode()
    {
        return ! empty($this->data) ? $this->data->status : $this->code;
    }

    /**
     * Get message
     * @return [type] [description]
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get Purchase data. Only return needed ones.
     *
     * @return [type] [description]
     */
    public function getData()
    {
        return $this->data;
    }

    public function setStatus($status = 0)
    {
        $this->data->status = $status;
    }
}
