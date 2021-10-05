<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Repository;

use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Pulsa;


class PulsaListRepository {

    private $pulsa = null;

    public function __construct()
    {

    }

    public function getSearchPulsa()
    {
        $sortByMapping = array(
            'pulsa_item_id'      => 'pulsa.pulsa_item_id',
            'pulsa_display_name' => 'pulsa.pulsa_display_name',
            'pulsa_code'         => 'pulsa.pulsa_code',
            'value'              => 'pulsa.value',
            'price'              => 'pulsa.price',
            'vendor_price'       => 'pulsa.vendor_price',
            'quantity'           => 'pulsa.quantity',
            'status'             => 'pulsa.status',
            'created_at'         => 'pulsa.created_at',
            'updated_at'         => 'pulsa.updated_at',
            'name'               => 'telco_operators.name',
            'is_promo'           => 'pulsa.is_promo',
        );

        $object_type = OrbitInput::get('object_type', 'pulsa');
        $sortBy = $sortByMapping[OrbitInput::get('sortby', 'status')];
        $sortMode = OrbitInput::get('sortmode', 'asc');

        $prefix = DB::getTablePrefix();

        $pulsa = Pulsa::select('pulsa.pulsa_item_id', 'pulsa.pulsa_code', 'pulsa.pulsa_display_name', 'telco_operators.name', 'pulsa.value', 'pulsa.price', 'pulsa.quantity', 'pulsa.status', 'pulsa.vendor_price', 'object_type', 'is_promo', 'pulsa.created_at', 'pulsa.updated_at')
                      ->leftJoin('telco_operators', 'telco_operators.telco_operator_id', '=', 'pulsa.telco_operator_id')
                      ->where('object_type', $object_type)
                      ->whereNotIn('pulsa.status', ['deleted']);

        // Filter pulsa by pulsa item id
        OrbitInput::get('pulsa_item_id', function ($pulsaItemId) use ($pulsa) {
            $pulsa->where('pulsa.pulsa_item_id', $pulsaItemId);
        });

        // Filter pulsa by pulsa_code
        OrbitInput::get('pulsa_code', function ($pulsa_code) use ($pulsa) {
            $pulsa->where('pulsa.pulsa_code', $pulsa_code);
        });

        // Filter pulsa by pulsa_code_like
        OrbitInput::get('pulsa_code_like', function ($pulsa_code) use ($pulsa) {
            $pulsa->where('pulsa.pulsa_code', 'like', "%{$pulsa_code}%");
        });

        // Filter pulsa by pulsa_display_name
        OrbitInput::get('pulsa_display_name', function ($pulsa_display_name) use ($pulsa) {
            $pulsa->where('pulsa.pulsa_display_name', $pulsa_display_name);
        });

        // Filter pulsa by pulsa_display_name_like
        OrbitInput::get('pulsa_display_name_like', function ($pulsa_display_name) use ($pulsa) {
            $pulsa->where('pulsa.pulsa_display_name', 'like', "%{$pulsa_display_name}%");
        });

        // Filter pulsa by telco_operators name
        OrbitInput::get('name', function ($name) use ($pulsa) {
            $pulsa->where('telco_operators.name', $name);
        });

        // Filter pulsa by telco_operators name
        OrbitInput::get('name_like', function ($name_like) use ($pulsa) {
            $pulsa->where('telco_operators.name', 'like', "%{$name_like}%");
        });

        // Filter pulsa by value
        OrbitInput::get('value', function($value) use ($pulsa)
        {
            if ($value !== '') {
                $pulsa->where('pulsa.value', $value);
            }
        });

        // Filter pulsa by price
        OrbitInput::get('price', function($price) use ($pulsa)
        {
            $pulsa->where('pulsa.price', $price);
        });

        // Filter pulsa by is_promo
        OrbitInput::get('is_promo', function($isPromo) use ($pulsa)
        {
            $pulsa->where('pulsa.is_promo', $isPromo);
        });

        // Filter pulsa by quantity
        OrbitInput::get('quantity', function($quantity) use ($pulsa)
        {
            $pulsa->where('pulsa.quantity', $quantity);
        });

        // Filter pulsa by status
        OrbitInput::get('status', function($status) use ($pulsa)
        {
            if ($status !== '') {
                $pulsa->where('pulsa.status', $status);
            }
        });

        $pulsa->orderBy($sortBy, $sortMode);

        return $pulsa;
    }

}