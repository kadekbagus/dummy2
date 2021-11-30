<?php

namespace Orbit\Notifications\DigitalProduct\ElectricityBill;

use Orbit\Notifications\DigitalProduct\BillReceiptNotification;

/**
 * electricity bill receipt notification.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReceiptNotification extends BillReceiptNotification
{
    protected $signature = 'electricity-bill-receipt-notification';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.electricity-bill.receipt',
            'text' => 'emails.digital-product.electricity-bill.receipt-text',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-receipt.electricity-bill.subject', [], '', 'id');
    }
}
