<?php

namespace Orbit\Notifications\DigitalProduct;

use Orbit\Notifications\DigitalProduct\BillNotification;

/**
 * Base bill receipt notification.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BillReceiptNotification extends BillNotification
{
    protected $signature = 'bill-receipt-notification';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.bill.receipt',
            'text' => 'emails.digital-product.bill.receipt-text',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-receipt.bill.subject', [], '', 'id');
    }
}
