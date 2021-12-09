<?php

namespace Orbit\Notifications\DigitalProduct\WaterBill;

use Orbit\Notifications\DigitalProduct\BillNotification;

/**
 * water bill receipt notification.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReceiptNotification extends BillNotification
{
    protected $signature = 'water-bill-receipt-notification';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.water-bill.receipt',
            'text' => 'emails.digital-product.water-bill.receipt-text',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-bill.receipt.subject', [], '', 'id');
    }

    protected function prepareEmailData($data = [])
    {
        return array_merge(parent::prepareEmailData($data), [
            'myWalletUrl' => $this->getMyPurchasesUrl('/pdam-bill'),
        ]);
    }
}
