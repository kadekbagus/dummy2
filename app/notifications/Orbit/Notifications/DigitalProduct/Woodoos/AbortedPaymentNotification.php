<?php namespace Orbit\Notifications\DigitalProduct\Woodoos;

use Config;
use Orbit\Notifications\DigitalProduct\AbortedPaymentNotification as BaseNotification;

/**
 * Email notification for Aborted Payment (Digital Product).
 *
 * @author Budi <budi@dominopos.com>
 */
class AbortedPaymentNotification extends BaseNotification
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
            'html' => 'emails.digital-product.woodoos.aborted-payment',
        ];
    }

    protected function getBuyUrl()
    {
        return Config::get('orbit.base_landing_page_url', 'https://www.gotomalls.com')
            . '/pln-token?country=Indonesia';
    }
}
