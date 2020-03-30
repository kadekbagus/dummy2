<?php namespace Orbit\Helper\Resource;

use Countable;

/**
 * Base class for a collection of resource.
 *
 * @author Budi <budi@gotomalls.com>
 */
class EloquentResourceCollection extends ResourceAbstract
{
    /**
     * The collection/eloquent collection.
     * @var Illuminate\Database\Eloquent\Collection
     */
    protected $collection = null;

    /**
     * The standard orbit api response for listing/collection.
     * It should have 3 properties: returned_records, total_records, and records.
     * @var array
     */
    protected $data = [];

    /**
     * Build the collection instance.
     *
     * @param array|Countable  $collection the collection
     * @param integer $total               total record.
     */
    public function __construct($collection)
    {
        $this->collection = $collection['records'];

        // Set initial collection data.
        $this->data = [
            'returned_records' => $collection['records']->count(),
            'total_records' => $collection['total'],
            'records' => [],
        ];
    }
}
