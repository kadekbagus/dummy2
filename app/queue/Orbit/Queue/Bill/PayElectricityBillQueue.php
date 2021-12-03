<?php

namespace Orbit\Queue\Bill;

use Orbit\Helper\MCash\API\Bill;
use Orbit\Notifications\DigitalProduct\ElectricityBill\ReceiptNotification;
use Orbit\Queue\Bill\PayBillQueue;

/**
 * A job to pay electricity bill after payment completed.
 *
 * @author Budi <budi@dominopos.com>
 */
class PayElectricityBillQueue extends PayBillQueue
{
    protected $billType = Bill::ELECTRICITY_BILL;

    protected $GMPId = 'Electricity Bill';

    protected function notifyReceipt($payment)
    {
        $payment->user->notify(new ReceiptNotification($payment));
    }
}
