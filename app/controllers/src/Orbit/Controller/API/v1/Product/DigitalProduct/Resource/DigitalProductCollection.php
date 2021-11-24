<?php

namespace Orbit\Controller\API\v1\Product\DigitalProduct\Resource;

use Config;
use Orbit\Helper\Resource\ResourceCollection;

/**
 * Resource Collection of Digital Product.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductCollection extends ResourceCollection
{
    /**
     * List of default product type. Will be merged with the ones
     * set on config/orbit.php.
     * @var array
     */
    private $productTypes = [
        'game_voucher' => 'Game Voucher',
        'electricity' => 'Electricity',
        'electricity_bill' => 'Electricity Bill',
        'pdam_bill' => 'PDAM Bill',
        'pbb_tax' => 'PBB Tax',
        'bpjs_bill' => 'BPJS Bill',
        'internet_provider_bill' => 'Internet Provider Bill'
    ];

    public function __construct($collection, $total = 0)
    {
        parent::__construct($collection, $total);

        $this->productTypes = array_merge(
            $this->productTypes,
            Config::get('orbit.digital_product.product_types', [])
        );
    }

    /**
     * Transform collection to array as response data.
     *
     * @return array
     */
    public function toArray()
    {
        foreach($this->collection as $item) {
            $this->data['records'][] = [
                'id' => $item->digital_product_id,
                'name' => $item->product_name,
                'type' => $this->transformProductType($item->product_type),
                'price' => $item->selling_price,
                'status' => $item->status,
                'provider_product' => $item->provider_product,
                'game_name' => $item->game_name
            ];
        }

        return $this->data;
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
