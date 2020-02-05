<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Activities;

use Orbit\Helper\Activity\PubActivity;

/**
 * Purchase Pending Activity.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseSuccessActivity extends PurchaseActivity
{
    private $productType = '';

    public function __construct($purchase, $productType = null)
    {
        parent::__construct($purchase);
        $this->productType = $productType;
    }

    protected function getAdditionalActivityData()
    {
        return [
            'activityNameLong' => 'Transaction is Successful',
            'notes' => $this->productType,
        ];
    }
}
