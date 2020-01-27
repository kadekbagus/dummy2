<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct\Resource;

use Config;
use DigitalProduct;
use Orbit\Helper\Resource\ResourceAbstract as Resource;
use Str;

/**
 * Resource Collection of Digital Product.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductCollection extends Resource
{
    private $collection = null;

    private $total = 0;

    private $productTypes = [
        'game_voucher' => 'Game Voucher',
        'electricity' => 'Electricity',
    ];

    public function __construct($collection, $total = 0)
    {
        $this->collection = $collection;
        $this->total = $total;
        $this->productTypes = array_merge($this->productTypes, Config::get('orbit.digital_product.product_types', []));
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
                'type' => $this->transformProductType($item->product_type),
                'price' => $item->selling_price,
                'status' => $item->status,
            ];
        }

        return $data;
    }

    /**
     * Transform product type.
     *
     * @param  [type] $productType [description]
     * @return [type]              [description]
     */
    private function transformProductType($productType)
    {
        return isset($this->productTypes[$productType])
            ? $this->productTypes[$productType]
            : $productType;
    }
}
