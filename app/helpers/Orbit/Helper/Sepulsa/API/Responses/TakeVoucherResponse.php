<?php

namespace Orbit\Helper\Sepulsa\API\Responses;

// use Orbit\Helper\Sepulsa\API\Responses\SepulsaResponse;

/**
 * Take Voucher response...
 */
class TakeVoucherResponse
{
    protected $code = 500;

    protected $message = 'Failed';

    protected $data = null;

    function __construct($object = null)
    {
        if (is_object($object)) {
            $this->data = $object;
            $this->message = $object->meta->message;
        }
        else {
            $this->message = $object;
        }
    }

    /**
     * Determine if Taken response is valid.
     *
     * @return boolean [description]
     */
    public function isValid()
    {
        if (is_null($this->data)) {
            return false;
        }

        return $this->data->meta->status;
    }

    /**
     * Means the order has been made in Sepulsa server.
     *
     * @return boolean [description]
     */
    public function isSuccess()
    {
        return ! empty($this->data->result) && $this->data->meta->status === true;
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
     * Get Taken Voucher data. Only return needed ones.
     *
     * @return [type] [description]
     */
    public function getVoucherData()
    {
        $data = [];

        foreach($this->data->result as $voucher) {
            $data[] = (object) [
                'id'               => $voucher->id,
                'code'             => $voucher->code,
                'expired_date'     => $voucher->expired_date,
                'redeem_url'       => $voucher->redeem_url,
                'taken_date'       => $voucher->taken_date,
            ];
        }

        return $data;
    }
}
