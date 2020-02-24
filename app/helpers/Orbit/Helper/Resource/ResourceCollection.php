<?php namespace Orbit\Helper\Resource;

/**
 * Base class for a collection of resource.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ResourceCollection extends ResourceAbstract
{
    /**
     * The collection/eloquent collection.
     * @var Illuminate\Database\Eloquent\Collection
     */
    protected $collection = null;

    /**
     * The total record of the collection (w/o the skip and take).
     * @var integer
     */
    protected $total = 0;

    /**
     * The standard orbit api response for listing/collection.
     * It should have 3 properties: returned_records, total_records, and records.
     * @var array
     */
    protected $data = [];

    public function __construct($collection, $total = 0)
    {
        $this->collection = $collection;
        $this->total = $total;

        // Set initial collection data.
        $this->data = [
            'returned_records' => $collection->count(),
            'total_records' => $total,
            'records' => [],
        ];
    }
}
