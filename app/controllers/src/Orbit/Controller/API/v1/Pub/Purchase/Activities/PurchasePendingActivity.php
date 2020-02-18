<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Activities;

use Orbit\Helper\Activity\PubActivity;

/**
 * Purchase Pending Activity.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchasePendingActivity extends PurchaseActivity
{
    public function __construct($purchase, $objectName = null)
    {
        $this->objectName = $objectName;
        parent::__construct($purchase);
    }

    protected function getAdditionalActivityData()
    {
        return [
            'activityNameLong' => 'Transaction is Pending',
        ];
    }
}
