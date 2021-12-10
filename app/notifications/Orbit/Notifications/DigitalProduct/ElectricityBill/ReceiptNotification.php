<?php

namespace Orbit\Notifications\DigitalProduct\ElectricityBill;

use Orbit\Notifications\DigitalProduct\BillNotification;

/**
 * electricity bill receipt notification.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReceiptNotification extends BillNotification
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
        return trans('email-receipt.subject', [], '', 'id');
    }

    protected function prepareEmailData($data = [])
    {
        return array_merge(parent::prepareEmailData($data), [
            'myWalletUrl' => $this->getMyPurchasesUrl('/bills'),
            'bill' => $this->getBillInformation(),
        ]);
    }
}
