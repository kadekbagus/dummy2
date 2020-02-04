<?php namespace Orbit\Controller\API\v1\Pub\Purchase\Activities;

use Orbit\Helper\Activity\PubActivity;

/**
 * Base Purchase Activity
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseActivity extends PubActivity
{
    protected $activityData = [
        'activityType' => 'transaction',
        'activityName' => 'transaction_status',
        'moduleName' => 'Midtrans Transaction',
    ];

    protected $location = null;

    private $purchase = null;

    private $product = null;

    protected $objectType = '';

    public function __construct($purchase, $product, $location = null)
    {
        parent::__construct(null, $purchase);

        $this->purchase = $purchase;
        $this->product = $product;
        $this->location = $location;
    }

    protected function getLocation()
    {
        return $this->location;
    }

    protected function getNotes()
    {
        $notes = '';

        if (isset($this->product->product_type)) {
            $notes = $this->product->product_type;
        }

        if (isset($this->product->object_type)) {
            $notes = $this->product->object_type;
        }

        return $notes;
    }
}
