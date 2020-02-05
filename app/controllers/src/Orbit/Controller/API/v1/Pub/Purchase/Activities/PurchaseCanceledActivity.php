<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Activities;

/**
 * Purchase Canceled Activity.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseCanceledActivity extends PurchaseActivity
{
    protected $responseSuccess = false;

    public function __construct($purchase, $objectName = '')
    {
        $this->objectName = $objectName;
        parent::__construct($purchase);
    }

    protected function getAdditionalActivityData()
    {
        return [
            'activityNameLong' => 'Transaction Canceled',
        ];
    }
}
