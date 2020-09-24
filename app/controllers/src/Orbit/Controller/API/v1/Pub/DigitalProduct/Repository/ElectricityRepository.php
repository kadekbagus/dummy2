<?php

namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Repository;

use DB;
use DigitalProduct;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Electricity repository.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class ElectricityRepository
{
    public function __construct()
    {

    }

    /**
     * Get collection based on requested filter.
     *
     * @return Illuminate\Database\Eloquent\Builder
     */
    public function getNominal()
    {
        $sortBy = OrbitInput::get('sortby', 'selling_price');
        $sortMode = OrbitInput::get('sortmode', 'asc');

        $electric = DigitalProduct::select('digital_products.digital_product_id',
                                           'digital_products.product_type',
                                           'digital_products.product_name',
                                           'digital_products.code as digital_product_code',
                                           'digital_products.status',
                                           'digital_products.is_displayed',
                                           'digital_products.is_promo',
                                           'digital_products.selling_price',
                                           'provider_products.provider_product_id',
                                           'provider_products.provider_name',
                                           'provider_products.code as provider_product_code',
                                           'provider_products.price',
                                           'provider_products.extra_field_metadata'
                                    )
                    ->leftJoin('provider_products', 'provider_products.provider_product_id', '=', 'digital_products.selected_provider_product_id')
                    ->where('digital_products.status', '=', 'active')
                    ->where('digital_products.product_type', '=', 'electricity')
                    ->orderBy($sortBy, $sortMode);

        return $electric;
    }

}
