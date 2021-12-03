<?php

namespace Orbit\Notifications\DigitalProduct\InternetProviderBill;

use Orbit\Notifications\DigitalProduct\BillNotification;

/**
 * internet provider bill receipt notification.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReceiptNotification extends BillNotification
{
    protected $signature = 'internet-provider-bill-receipt-notification';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.internet-provider-bill.receipt',
            'text' => 'emails.digital-product.internet-provider-bill.receipt-text',
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
