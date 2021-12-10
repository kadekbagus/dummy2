<?php

namespace Orbit\Notifications\DigitalProduct\PBBTaxBill;

use Orbit\Notifications\DigitalProduct\BillNotification;

/**
 * PBB Tax bill receipt notification.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReceiptNotification extends BillNotification
{
    protected $signature = 'pbb-tax-bill-receipt-notification';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.pbb-tax-bill.receipt',
            'text' => 'emails.digital-product.pbb-tax-bill.receipt-text',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-bill.receipt.subject', [], '', 'id');
    }

    protected function prepareEmailData($data = [])
    {
        return array_merge(parent::prepareEmailData($data), [
            'myWalletUrl' => $this->getMyPurchasesUrl('/pbb-tax-bill'),
        ]);
    }
}
