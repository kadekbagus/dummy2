<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Repository;

use Carbon\Carbon;
use Config;
use DB;
use DigitalProduct;
use Log;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Product\DigitalProduct\Resource\DigitalProductCollection;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Helper\MediaQuery;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Resource\DigitalProductResource;
use User;

/**
 * Digital Product repository.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductRepository
{
    use MediaQuery;

    function __construct()
    {
        $this->setupImageUrlQuery();
    }

    public function findProducts()
    {
        $skip = OrbitInput::get('skip', 0);
        $take = OrbitInput::get('take', 10);
        $sortBy = OrbitInput::get('sortby', 'status');
        $sortMode = OrbitInput::get('sortmode', 'asc');

        $digitalProducts = DigitalProduct::select(
            'digital_product_id',
            'product_name',
            'selling_price',
            'product_type',
            'status'
        );

        OrbitInput::get('product_type', function($productType) use ($digitalProducts) {
            if (! empty($productType)) {
                $digitalProducts->where('product_type', $productType);
            }
        });

        OrbitInput::get('keyword', function($keyword) use ($digitalProducts) {
            if (! empty($keyword)) {
                $digitalProducts->where('product_name', 'like', "%{$keyword}%");
            }
        });

        OrbitInput::get('status', function($status) use ($digitalProducts) {
            if (! empty($status)) {
                $digitalProducts->where('status', $status);
            }
        });

        $total = clone $digitalProducts;
        $total = $total->count();

        $digitalProducts->orderBy($sortBy, $sortMode);

        $digitalProducts = $digitalProducts->skip($skip)->take($take)->get();

        return new DigitalProductCollection($digitalProducts, $total);
    }

    private function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
