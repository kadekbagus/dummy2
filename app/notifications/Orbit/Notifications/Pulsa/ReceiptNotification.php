<?php namespace Orbit\Notifications\Pulsa;

use Orbit\Notifications\Payment\ReceiptNotification as BaseReceiptNotification;

/**
 * Receipt Notification for Customer after purchasing Hot Deals (Paid Coupon).
 *
 * @todo  delete this notification class since we use one template
 *        for both sepulsa and hot deals.
 */
class ReceiptNotification extends BaseReceiptNotification
{
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.pulsa.receipt',
        ];
    }
}
