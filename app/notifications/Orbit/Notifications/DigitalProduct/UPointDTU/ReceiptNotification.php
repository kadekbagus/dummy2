<?php namespace Orbit\Notifications\DigitalProduct\UPointDTU;

use Orbit\Notifications\DigitalProduct\ReceiptNotification as BaseReceiptNotification;

/**
 * Receipt Notification for Customer after purchasing Pulsa.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReceiptNotification extends BaseReceiptNotification
{
    protected $signature = 'digital-product-upoint-dtu-receipt-notification';

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.upoint.dtu-receipt',
        ];
    }
}
