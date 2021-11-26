<?php

namespace Orbit\Queue\Bill;

use Config;
use Orbit\Helper\MCash\API\Bill;
use Orbit\Queue\Bill\PayBillQueue;
use User;

/**
 * A job to pay electricity bill after payment completed.
 *
 * @author Budi <budi@dominopos.com>
 */
class PayElectricityBillQueue extends PayBillQueue
{
    protected $billType = Bill::ELECTRICITY_BILL;

    protected $GMPId = 'Electricity Bill';

    protected function notifyReceipt($payment, $billPayment)
    {
        $payment->user->notify(new ReceiptNotification(
            $payment,
            $billPayment->getBillInformation()
        ));
    }

    protected function notifyFailed($payment, $e)
    {
        $adminEmails = Config::get('orbit.transaction.notify_emails', ['developer@dominopos.com']);

        // Notify admin for this failure.
        foreach($adminEmails as $email) {
            $admin              = new User;
            $admin->email       = $email;
            $admin->notify(new DigitalProductNotAvailableNotification($payment, $e->getMessage()));
        }

        // Notify customer that coupon is not available.
        $payment->user->notify(new CustomerDigitalProductNotAvailableNotification($payment));
    }

    protected function recordSuccessActivity($payment)
    {
        $payment->user->activity(new PurchaseSuccessActivity($payment, $this->objectType));
    }

    protected function recordFailedActivity($payment, $notes)
    {
        $payment->user->activity(new PurchaseFailedProductActivity(
            $payment,
            $this->objectType,
            $notes
        ));
    }
}
