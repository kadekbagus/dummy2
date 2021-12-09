<?php

namespace Orbit\Queue\Bill;

use Orbit\Helper\MCash\API\Bill;
use Orbit\Notifications\DigitalProduct\PBBTaxBill\ReceiptNotification;
use Orbit\Queue\Bill\PayBillQueue;

/**
 * A job to pay pbb bill after payment completed.
 *
 * @author Budi <budi@dominopos.com>
 */
class PayPbbTaxBillQueue extends PayBillQueue
{
    protected $billType = Bill::PBB_TAX_BILL;

    protected $GMPId = 'PBB Tax Bill';

    protected function notifyReceipt($payment)
    {
        $payment->user->notify(new ReceiptNotification($payment));
    }
}
