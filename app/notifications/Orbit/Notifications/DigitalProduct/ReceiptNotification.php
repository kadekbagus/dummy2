<?php namespace Orbit\Notifications\DigitalProduct;

use Orbit\Notifications\Payment\ReceiptNotification as BaseReceiptNotification;

/**
 * Receipt Notification for Customer after purchasing Pulsa.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReceiptNotification extends BaseReceiptNotification
{
    protected $voucherData = '';

    protected $signature = 'digital-product-receipt-notification';

    function __construct($payment = null, $voucherData = [])
    {
        parent::__construct($payment);
        $this->voucherData = $voucherData;
    }

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.receipt',
        ];
    }

    /**
     * Only send email notification at the moment.
     *
     * @override
     * @return [type] [description]
     */
    protected function notificationMethods()
    {
        // Set to notify via email
        return ['email'];
    }

    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'myWalletUrl' => $this->getMyPurchasesUrl('/game-voucher'),
            'voucherData' => $this->voucherData,
        ]);
    }
}
