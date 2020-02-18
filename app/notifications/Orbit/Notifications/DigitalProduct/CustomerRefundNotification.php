<?php namespace Orbit\Notifications\DigitalProduct;

use Orbit\Notifications\Pulsa\CustomerRefundNotification as BaseNotification;

/**
 * Notify Customers that we refunded the their payment.
 *
 * @author Budi <budi@dominopos.com>
 */
class CustomerRefundNotification extends BaseNotification
{
    /**
     * Get the email templates that will be used.
     *
     * @return [type] [description]
     */
    public function getEmailTemplates()
    {
        return [
            'html' => 'emails.digital-product.customer-payment-refunded',
        ];
    }

    public function getEmailSubject()
    {
        return trans('email-customer-refund.subject_digital_product', [], '', 'id');
    }
}
