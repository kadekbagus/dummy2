<?php namespace Orbit\Notifications\DigitalProduct;

use Orbit\Notifications\Payment\ReceiptNotification as BaseReceiptNotification;

/**
 * Receipt Notification for Customer after purchasing Pulsa.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReceiptNotification extends BaseReceiptNotification
{
    protected $serialNumber = null;

    function __construct($payment = null, $serialNumber = null)
    {
        parent::__construct($payment);
        $this->serialNumber = $serialNumber;
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
            'myWalletUrl' => $this->getMyPurchasesUrl('/coupons'),
        ]);
    }
}
