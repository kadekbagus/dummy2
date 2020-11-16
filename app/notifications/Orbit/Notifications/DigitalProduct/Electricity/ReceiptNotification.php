<?php namespace Orbit\Notifications\DigitalProduct\Electricity;

use Orbit\Notifications\DigitalProduct\ReceiptNotification as BaseReceiptNotification;

/**
 * Receipt Notification for Customer after purchasing Pulsa.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReceiptNotification extends BaseReceiptNotification
{
    protected $voucherData = [];

    protected $signature = 'electricity-receipt-notification';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.electricity.receipt',
        ];
    }

    protected function getVoucherData()
    {
        if (!empty($this->voucherData) && is_string($this->voucherData)) {
            return ['TOKEN' => $this->voucherData];
        }
    }

    public function getMyPurchasesUrl($path = '')
    {
        return parent::getMyPurchasesUrl('/pln?country=Indonesia');
    }
}
