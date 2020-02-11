<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Activities;

use Orbit\Helper\Activity\PubActivity;

/**
 * Purchase failed after trying to purchase product from Provider.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseFailedProductActivity extends PurchaseActivity
{
    protected $responseSuccess = false;

    public function __construct($purchase, $productType, $notes)
    {
        parent::__construct($purchase);

        $this->mergeActivityData([
            'activityNameLong' => "Transaction is Success - Failed Getting {$productType}",
            'notes' => $notes
        ]);
    }
}
