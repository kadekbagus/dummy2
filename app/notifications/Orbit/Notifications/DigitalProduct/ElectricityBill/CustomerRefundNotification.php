<?php

namespace Orbit\Notifications\DigitalProduct\ElectricityBill;

use Orbit\Notifications\DigitalProduct\CustomerRefundNotification as BaseNotification;

/**
 * Notify Customers that we refunded the their payment.
 *
 * @author Budi <budi@dominopos.com>
 */
class CustomerRefundNotification extends BaseNotification
{
    protected $signature = 'electricity-bill-customer-refund-transaction';
}
