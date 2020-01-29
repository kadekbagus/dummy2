<?php namespace Orbit\Notifications\DigitalProduct;

use Orbit\Notifications\Payment\CanceledPaymentNotification as BaseNotification;

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
            'html' => 'emails.digital-product.canceled-payment',
        ];
    }

    protected function getEmailSubject()
    {
        return trans('email-canceled-payment.subject_digital_product', ['productType' => $this->resolveProductType()], '', 'id');
    }
}
