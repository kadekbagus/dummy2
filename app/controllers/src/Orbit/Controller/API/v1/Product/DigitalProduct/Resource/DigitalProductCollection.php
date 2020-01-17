<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct\Resource;

use DigitalProduct;
use Orbit\Helper\Resource\ResourceAbstract as Resource;

/**
 * Resource Collection of Digital Product.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductCollection extends Resource
{
    private $collection = null;

    private $total = 0;

    public function __construct($collection, $total = 0)
    {
        $this->collection = $collection;
        $this->total = $total;
    }

    /**
     * Transform collection to array as response data.
     *
     * @return [type] [description]
     */
    public function toArray()
    {
        $data = [
            'returned_records' => $this->collection->count(),
            'total_records' => $this->total,
            'records' => [],
        ];

        foreach($this->collection as $item) {
            $data['records'][] = [
                'id' => $item->digital_product_id,
                'name' => $item->product_name,
                'type' => $item->product_type,
                'status' => $item->status,
            ];
        }

        return $data;
    }
}
