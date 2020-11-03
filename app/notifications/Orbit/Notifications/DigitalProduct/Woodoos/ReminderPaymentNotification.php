<?php namespace Orbit\Notifications\DigitalProduct\Woodoos;

use Orbit\Notifications\Payment\DigitalProduct\ReminderPaymentNotification as BaseNotification;

/**
 * A reminder notification, that will be fired up at midnight to remind Customer
 * to complete the payment before expired.
 *
 * @author Budi <budi@dominopos.com>
 */
class ReminderPaymentNotification extends BaseNotification
{
    /**
     * Get the email data.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'cancelUrl' => $this->getCancelUrl() . "&type=electricity",
            'myWalletUrl' => $this->getMyPurchasesUrl('/electricity'),
        ]);
    }
}
