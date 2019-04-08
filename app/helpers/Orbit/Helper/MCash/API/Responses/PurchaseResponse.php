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
        return ! empty($this->data) && $this->data->status === 0;
    }

    /**
     * Pending?
     *
     * @return boolean [description]
     */
    public function isPending()
    {
        return ! empty($this->data) && $this->data->status != 0;
    }

    public function isNotAvailable()
    {
        return ! empty($this->data) && $this->data->status === 413;
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
