<?php namespace Orbit\Activities\Pub\Payment;

use Orbit\Activities\Pub\Payment\PaymentActivity;

/**
 * Transaction Failed Activity.
 */
class TransactionFailedActivity extends PaymentActivity
{
    protected $responseSuccess = false;

    /**
     * @override
     */
    protected function addAdditionalData()
    {
        $this->activityData = array_merge($this->activityData, [
            'notes' => 'Transaction is failed from Midtrans/Customer.',
            'activityNameLong' => 'Transaction is Failed',
            'moduleName' => 'Midtrans Transaction',
        ]);
    }
}
