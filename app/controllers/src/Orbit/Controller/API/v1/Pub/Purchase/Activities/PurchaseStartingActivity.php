<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Activities;

/**
 * Purchase Starting Activity.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseStartingActivity extends PurchaseActivity
{
    public function __construct($purchase, $objectType = '')
    {
        parent::__construct($purchase);
        $this->objectType = $objectType;
    }

    protected function getAdditionalActivityData()
    {
        return [
            'activityNameLong' => 'Transaction is Starting',
            'notes' => $this->objectType,
        ];
    }
}
