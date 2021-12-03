<?php

namespace Orbit\Controller\API\v1\Pub\Purchase\Bill;

use Orbit\Controller\API\v1\Pub\Purchase\BaseUpdatePurchase;
use Orbit\Notifications\DigitalProduct\PBBTaxBill\AbortedPaymentNotification;
use Orbit\Notifications\DigitalProduct\PBBTaxBill\CanceledPaymentNotification;
use Orbit\Notifications\DigitalProduct\PBBTaxBill\CustomerRefundNotification;
use Orbit\Notifications\DigitalProduct\PBBTaxBill\ExpiredPaymentNotification;
use Orbit\Notifications\DigitalProduct\PBBTaxBill\PendingPaymentNotification;

/**
 * PBB tax bill update purchase handler.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PBBTaxBillUpdatePurchase extends BaseUpdatePurchase
{
    protected function notifyAbortedPurchase()
    {
        $this->purchase->user->notify(
            new AbortedPaymentNotification($this->purchase)
        );
    }

    protected function notifyPendingPurchase()
    {
        $this->purchase->user->notify(
            new PendingPaymentNotification($this->purchase),
            30
        );
    }

    protected function notifyCancelledPurchase()
    {
        $this->purchase->user->notify(
            new CanceledPaymentNotification($this->purchase)
        );
    }

    protected function notifyExpiredPurchase()
    {
        $this->purchase->user->notify(
            new ExpiredPaymentNotification($this->purchase)
        );
    }

    protected function notifyRefundedPurchase()
    {
        // Send refund notification to customer.
        if ($this->shouldNotifyRefund) {
            $this->purchase->user->notify(
                new CustomerRefundNotification(
                    $this->purchase, $this->refundReason
                )
            );
        }
    }
}
