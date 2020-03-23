<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Activities;

use Orbit\Helper\Activity\PubActivity;

/**
 * Purchase Aborted Activity.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseAbortedActivity extends PurchaseActivity
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
            'activityNameLong' => 'Transaction is Aborted',
            'notes' => 'Digital Product Transaction aborted by customer.',
        ];
    }
}
