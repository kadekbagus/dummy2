<?php namespace Orbit\Notifications\DigitalProduct;

use Orbit\Notifications\Payment\BeforeExpiredPaymentNotification;

/**
 * A reminder notification, that will be fired up at midnight to remind Customer
 * to complete the payment before expired.
 *
 * @author Budi <budi@dominopos.com>
 */
class ReminderPaymentNotification extends BeforeExpiredPaymentNotification
{
    /**
     * Get the email templates.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.payment-reminder',
        ];
    }
}
