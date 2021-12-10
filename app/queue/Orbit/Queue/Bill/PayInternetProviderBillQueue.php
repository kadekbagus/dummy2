<?php

namespace Orbit\Queue\Bill;

use Orbit\Helper\MCash\API\Bill;
use Orbit\Notifications\DigitalProduct\InternetProviderBill\ReceiptNotification;
use Orbit\Queue\Bill\PayBillQueue;

/**
 * A job to pay ISP bill after payment completed.
 *
 * @author Budi <budi@dominopos.com>
 */
class PayInternetProviderBillQueue extends PayBillQueue
{
    protected $billType = Bill::ISP_BILL;

    protected $GMPId = 'Internet Provider Bill';

    protected function notifyReceipt($payment)
    {
        $payment->user->notify(new ReceiptNotification($payment));
    }
}
