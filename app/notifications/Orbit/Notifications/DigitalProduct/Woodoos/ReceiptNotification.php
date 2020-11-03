<?php namespace Orbit\Notifications\DigitalProduct\Woodoos;

use Orbit\Notifications\DigitalProduct\ReceiptNotification as BaseReceiptNotification;

/**
 * Receipt Notification for Customer after purchasing Pulsa.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReceiptNotification extends BaseReceiptNotification
{
    protected $voucherData = [];

    protected $signature = 'woodoos-receipt-notification';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.woodoos.receipt',
        ];
    }

    public function getMyPurchasesUrl($path = '')
    {
        return parent::getMyPurchasesUrl('/electricity?country=Indonesia');
    }
}
