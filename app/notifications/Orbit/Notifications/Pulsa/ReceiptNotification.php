<?php namespace Orbit\Notifications\Pulsa;

use Orbit\Notifications\Payment\ReceiptNotification as BaseReceiptNotification;

/**
 * Receipt Notification for Customer after purchasing Pulsa.
 *
 * @todo  delete this notification class since we use one template
 *        for both sepulsa and hot deals.
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
            'html' => 'emails.pulsa.receipt',
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
        // Set to notify via email and InApp
        return ['email'];
    }

    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'myWalletUrl' => $this->getMyPurchasesUrl() . '/pulsa',
        ]);
    }
}
