<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Activities;

use Orbit\Helper\Activity\PubActivity;

/**
 * Purchase Expired Activity.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseExpiredActivity extends PurchaseActivityWithObjectName
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
            'activityNameLong' => 'Transaction is Expired',
            'notes' => 'Transaction is expired from Midtrans.',
        ];
    }
}
