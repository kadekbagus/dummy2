<?php namespace Orbit\Notifications\Pulsa;

use Orbit\Notifications\Payment\ReceiptNotification as BaseReceiptNotification;
use PaymentTransaction;

/**
 * Receipt Notification for Customer after purchasing Pulsa.
 *
 * @todo  delete this notification class since we use one template
 *        for both sepulsa and hot deals.
 */
class ReceiptNotification extends BaseReceiptNotification
{
    protected $serialNumber = null;

    protected $notificationDelay = 10;

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

    /**
     * Send the receipt if we have to.
     *
     * @param  [type] $job  [description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function toEmail($job, $data)
    {
        if ($this->shouldSendReceipt($data)) {
            parent::toEmail($job, $data);
        }
    }

    /**
     * Determine if we should send receipt or not.
     *
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    private function shouldSendReceipt($data)
    {
        // Fetch latest payment status from db.
        $payment = PaymentTransaction::onWriteConnection()->select('status')->findOrFail($data['transaction']['id']);

        return in_array($payment->status, [PaymentTransaction::STATUS_SUCCESS]);
    }
}
