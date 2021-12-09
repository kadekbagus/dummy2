<?php

namespace Orbit\Queue\Bill;

use Orbit\Helper\MCash\API\Bill;
use Orbit\Notifications\DigitalProduct\BpjsBill\ReceiptNotification;
use Orbit\Queue\Bill\PayBillQueue;

/**
 * A job to pay bpjs bill after payment completed.
 *
 * @author Budi <budi@dominopos.com>
 */
class PayBpjsBillQueue extends PayBillQueue
{
    protected $billType = Bill::BPJS_BILL;

    protected $GMPId = 'BPJS Bill';

    protected function notifyReceipt($payment)
    {
        $payment->user->notify(new ReceiptNotification($payment));
    }
}
