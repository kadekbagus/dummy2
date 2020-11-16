<?php namespace Orbit\Notifications\DigitalProduct\Electricity;

use Config;
use Orbit\Notifications\DigitalProduct\ExpiredPaymentNotification as BaseNotification;

/**
 * Email notification for Expired Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class ExpiredPaymentNotification extends BaseNotification
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
            'html' => 'emails.digital-product.electricity.expired-payment',
        ];
    }

    protected function getBuyUrl()
    {
        return Config::get('orbit.base_landing_page_url', 'https://www.gotomalls.com')
            . '/pln-token?country=Indonesia';
    }
}
