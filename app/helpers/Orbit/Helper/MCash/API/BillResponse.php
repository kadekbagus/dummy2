<?php

namespace Orbit\Helper\MCash\API;

/**
 * Base bill response.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BillResponse
{
    protected $code = 500;

    protected $message = 'Failed';

    protected $data = null;

    protected $billInformation = null;

    /**
     * List of status considered as retry-able.
     *
     * @var [type]
     */
    protected $retryStatus = [609, 413, 8701];

    /**
     * List of error status that will be displayed in the admin email.
     *
     * @var [type]
     */
    protected $displayableErrorStatus = [627, 617, 628];

    /**
     * Maximum number of retry we would do if the first time was failed.
     * @var integer
     */
    protected $maxRetry = 10;

    function __construct($object = null)
    {
        if (is_object($object)) {
            $this->data = $object;
            $this->message = $object->message;
        }
        else {
            $this->message = $object;
        }

        $this->parseResponse();
    }

    /**
     * Get bill information.
     *
     * @return [type] [description]
     */
    public function getBillInformation()
    {
        return $this->billInformation;
    }

    /**
     * Determine if purchase is success or not.
     *
     * @return boolean [description]
     */
    public function isSuccess()
    {
        return ! empty($this->data)
            && $this->data->status === 0
            && ((isset($this->data->pending) && $this->data->pending === 0)
                || (! isset($this->data->pending)));
    }

    public function hasBillingInformation()
    {
        return $this->isSuccess() && isset($this->data->data)
            && ! empty($this->data->data)
            && isset($this->data->data->billing_id);
    }

    /**
     * Determine if purchase is success but without Serial Number (SN),
     * which most of time will result to a failed purchase from MCash.
     *
     * @return boolean [description]
     */
    public function isSuccessWithoutSN()
    {
        return $this->isSuccess()
                && isset($this->data->data)
                && isset($this->data->data->serial_number)
                && empty($this->data->data->serial_number);
    }

    /**
     * Get serial number from purchase.
     *
     * @return boolean [description]
     */
    public function getSerialNumber()
    {
        return isset($this->data->data) && ! empty($this->data->data)
            && isset($this->data->data->serial_number) && ! empty($this->data->data->serial_number)
            ? $this->data->data->serial_number
            : null;
    }

    /**
     * Get the inquiry_id from response.
     *
     * @return int|string|null the inquiry_id or null if not exists.
     */
    public function getInquiryId()
    {
        return isset($this->data->inquiry_id)
            && ! empty($this->data->inquiry_id)
            ? $this->data->inquiry_id
            : null;
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

    /**
     * Determine if we should retry the purchase when we get specific
     * response code from MCash.
     *
     * @param  [type] $retry [description]
     * @return [type]        [description]
     */
    public function shouldRetry($retry = 1)
    {
        return $retry < $this->maxRetry
                && (empty($this->data)
                    || (! empty($this->data)
                        && in_array($this->data->status, $this->retryStatus)));
    }

    /**
     * Determine if we has reached the maximum number of retry allowed.
     *
     * @param  integer $retry [description]
     * @return [type]         [description]
     */
    public function maxRetryReached($retry = 1)
    {
        return $retry === $this->maxRetry;
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

    /**
     * Set response data.
     *
     * @param array $data [description]
     */
    public function setData($data = [])
    {
        foreach($data as $key => $value) {
            $this->data->{$key} = $value;
        }
    }

    /**
     * Unset response data keys.
     *
     * @param  array  $keys [description]
     * @return [type]       [description]
     */
    public function unsetData($keys = [])
    {
        foreach($keys as $key) {
            if (isset($this->data->{$key})) {
                unset($this->data->{$key});
            }
        }
    }

    /**
     * Determine if current (error) purchase response should be displayed in
     * the admin notification email and then return the formatted error message.
     *
     * @return [type] [description]
     */
    public function getFailureMessage()
    {
        $displayErrorInAdminEmail = ! empty($this->data) && isset($this->data->status)
                && in_array((int) $this->data->status, $this->displayableErrorStatus);

        $failureMessage = "Pulsa purchase is FAILED, unknown status from MCASH.";
        if ($displayErrorInAdminEmail) {
            $failureMessage = sprintf(
                "ERR [%s] : %s",
                $this->data->status,
                $this->data->message
            );
        }

        return $failureMessage;
    }
}
