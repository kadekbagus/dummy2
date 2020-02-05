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

    protected $objectName = '';

    protected $objectType = '';

    protected $location = null;

    protected $purchase = null;

    protected $product = null;

    public function __construct($purchase, $product = null, $location = null)
    {
        parent::__construct(null, $purchase);

        $this->purchase = $purchase;
        $this->product = $product;
        $this->location = $location;

        // Merge common/basic purchase activity data.
        $this->mergeActivityData([
            'currentUrl' => $this->getCurrentUtmUrl(),
            'location' => $this->getLocation(),
        ]);
    }

    protected function getLocation()
    {
        return $this->location;
    }

    protected function getNotes()
    {
        if (empty($this->product)) {
            return '';
        }

        $notes = '';

        if (isset($this->product->product_type)) {
            $notes = $this->product->product_type;
        }

        if (isset($this->product->object_type)) {
            $notes = $this->product->object_type;
        }

        return $notes;
    }

    protected function getCurrentUtmUrl()
    {
        return isset($this->purchase->current_utm_url)
            ? $this->purchase->current_utm_url
            : null;
    }
}
