<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Activities;

/**
 * Purchase Starting Activity.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseStartingActivity extends PurchaseActivity
{
    protected function getAdditionalActivityData()
    {
        return [
            'activityNameLong' => 'Transaction is Starting',
            'location' => $this->getLocation(),
            'notes' => $this->getNotes(),
        ];
    }
}
