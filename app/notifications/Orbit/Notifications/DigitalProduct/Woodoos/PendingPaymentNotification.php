<?php namespace Orbit\Notifications\DigitalProduct\Woodoos;

use Orbit\Notifications\DigitalProduct\PendingPaymentNotification as Base;

/**
 * Pending Payment Notification for Digital Product.
 *
 * @author Budi <budi@dominopos.com>
 */
class PendingPaymentNotification extends Base
{
    /**
     * Signature/ID for this notification.
     * @var string
     */
    protected $signature = 'woodoos-pending-transaction';

    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'cancelUrl' => $this->getCancelUrl() . "&type=pln-token",
            'myWalletUrl' => $this->getMyPurchasesUrl('/pln?country=Indonesia'),
        ]);
    }
}
