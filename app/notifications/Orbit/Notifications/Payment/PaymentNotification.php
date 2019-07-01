<?php

namespace Orbit\Notifications\Payment;

use Orbit\Helper\Notifications\CustomerNotification;

/**
 * Base Payment Notification.
 */
class PaymentNotification extends CustomerNotification
{
    protected $context = 'transaction';
}
