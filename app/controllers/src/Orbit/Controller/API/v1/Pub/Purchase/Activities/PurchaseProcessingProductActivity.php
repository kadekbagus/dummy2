<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Activities;

use Orbit\Helper\Activity\PubActivity;

/**
 * Purchase with success transaction and currently processing/purchasing
 * product from vendor Activity.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseProcessingProductActivity extends PurchaseActivity
{
    public function __construct($purchase, $objectName, $objectType)
    {
        parent::__construct($purchase);

        $this->mergeActivityData([
            'activityNameLong' => "Transaction is Success - Getting {$objectType}",
            'notes' => $objectName,
        ]);
    }
}
