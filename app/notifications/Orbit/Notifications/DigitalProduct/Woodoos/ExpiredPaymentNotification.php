<?php namespace Orbit\Notifications\DigitalProduct\Woodoos;

use Orbit\Notifications\Payment\DigitalProduct\ExpiredPaymentNotification as BaseNotification;

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
            'html' => 'emails.digital-product.expired-payment',
        ];
    }

    public function getEmailSubject()
    {
        $productType = trans("email-payment.product_type.{$this->productType}", [], '', 'id');
        return trans('email-expired-payment.subject_digital_product', ['productType' => $productType], '', 'id');
    }

    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'productType' => $this->productType,
        ]);
    }

    protected function getBuyUrl()
    {
        return Config::get('orbit.base_landing_page_url', 'https://www.gotomalls.com')
            . '/pln-token?country=Indonesia';
    }
}
