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

    protected $voucherCode = null;

    protected $signature = 'digital-product-receipt-notification';

    function __construct($payment = null, $serialNumber = null, $voucherCode = null)
    {
        parent::__construct($payment);
        $this->serialNumber = $serialNumber;
        $this->voucherCode = $voucherCode;
    }

    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.receipt',
        ];
    }

    // protected function getSerialNumber()
    // {
    //     $serialNumber = parent::getSerialNumber();
    //     $serialNumber .= isset($this->voucherCode)  && ! empty($this->voucherCode)
    //         ? "<br>Voucher Code: {$this->voucherCode}"
    //         : '';

    //     return $serialNumber;
    // }

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
