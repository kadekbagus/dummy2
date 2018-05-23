<?php 

namespace Orbit\Helper\Sepulsa\API\Responses;

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
        return ! empty($this->data->result);
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
        $data = new \stdClass;

        // Only 1 voucher should exists in the result.
        foreach($this->data->result as $voucher) {
            $data->code             = $voucher->code;
            $data->expired_date     = $voucher->voucher->expired_date;
            $data->redeem_url       = $voucher->redeem_url;
        }

        return $data;
    }
}