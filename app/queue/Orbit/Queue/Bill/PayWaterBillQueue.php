<?php

namespace Orbit\Queue\Bill;

use Orbit\Helper\MCash\API\Bill;
use Orbit\Notifications\DigitalProduct\WaterBill\ReceiptNotification;
use Orbit\Queue\Bill\PayBillQueue;

/**
 * A job to pay water bill after payment completed.
 *
 * @author Budi <budi@dominopos.com>
 */
class PayWaterBillQueue extends PayBillQueue
{
    protected $billType = Bill::PDAM_BILL;

    protected $GMPId = 'Water Bill';

    protected function notifyReceipt($payment)
    {
        $payment->user->notify(new ReceiptNotification($payment));
    }
}
