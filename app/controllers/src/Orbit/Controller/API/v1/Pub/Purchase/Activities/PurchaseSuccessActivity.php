<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Activities;

use Orbit\Helper\Activity\PubActivity;

/**
 * Purchase Pending Activity.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseSuccessActivity extends PurchaseActivity
{
    public function __construct($purchase, $productType = null)
    {
        parent::__construct($purchase);

        $this->mergeActivityData([
            'activityNameLong' => 'Transaction is Successful',
            'notes' => $productType,
        ]);
    }
}
