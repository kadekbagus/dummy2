<?php namespace Orbit\Notifications\DigitalProduct\Woodoos;

use Config;
use Orbit\Notifications\DigitalProduct\CanceledPaymentNotification as BaseNotification;

/**
 * Email notification for Canceled Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class CanceledPaymentNotification extends BaseNotification
{
    /**
     * Get the email templates.
     * Can be overriden in each receipt class if needed.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.woodoos.canceled-payment',
        ];
    }

    protected function getBuyUrl()
    {
        return Config::get('orbit.base_landing_page_url', 'https://www.gotomalls.com')
            . '/pln-token?country=Indonesia';
    }

    /**
     * Get email data with pulsa phone number.
     *
     * @return [type] [description]
     */
    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'pulsaPhoneNumber' => $this->payment->extra_data,
        ]);
    }
}
