<?php namespace Orbit\Notifications\Pulsa;

use Orbit\Notifications\Pulsa\ReceiptNotification as BaseReceiptNotification;

/**
 * Receipt Notification for Customer after purchasing Pulsa,
 * but with Pending status from MCash.
 *
 * @author Budi <budi@dominopos.com>
 */
class CustomerPulsaPendingNotification extends BaseReceiptNotification
{
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.pulsa.receipt-pending',
        ];
    }
}
