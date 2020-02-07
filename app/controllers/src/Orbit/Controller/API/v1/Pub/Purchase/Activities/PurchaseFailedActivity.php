<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Activities;

use Orbit\Helper\Activity\PubActivity;

/**
 * Purchase Failed Activity.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseFailedActivity extends PurchaseActivity
{
    protected $responseSuccess = false;

    protected function getAdditionalActivityData()
    {
        return [
            'activityNameLong' => 'Transaction is Failed',
            'notes' => 'Transaction is failed from Midtrans/Customer.',
        ];
    }
}
