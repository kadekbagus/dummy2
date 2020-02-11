<?php namespace Orbit\Notifications\DigitalProduct;

use Orbit\Notifications\Payment\AbortedPaymentNotification as BaseNotification;

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
            'html' => 'emails.digital-product.aborted-payment',
        ];
    }

    protected function getEmailSubject()
    {
        return trans('email-aborted-payment.subject_digital_product', ['productType' => $this->resolveProductType()], '', 'id');
    }

    public function getEmailData()
    {
        return array_merge(parent::getEmailData(), [
            'productType' => $this->productType,
        ]);
    }
}
