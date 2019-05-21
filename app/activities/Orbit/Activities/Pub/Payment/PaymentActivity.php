<?php namespace Orbit\Activities\Pub\Payment;

use Orbit\Activities\Pub\PubActivity;

/**
 * Base Payment related Activity
 */
class PaymentActivity extends PubActivity
{
    protected $activityData = [
        'activityType' => 'transaction',
        'activityName' => 'transaction_status',
    ];
}
