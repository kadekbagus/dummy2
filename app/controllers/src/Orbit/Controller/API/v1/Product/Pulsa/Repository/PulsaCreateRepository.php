<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Repository;

use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Pulsa;


class PulsaCreateRepository {

    private $pulsa = null;

    public function __construct()
    {

    }

    public function save($request)
    {
        DB::transaction(function() use ($request)
        {
            $this->pulsa = new Pulsa;

            $this->createModelFromRequest($request);

            $this->pulsa->save();
        });

        return $this->pulsa;
    }

    private function createModelFromRequest($request)
    {
        $this->pulsa->telco_operator_id = $request->telco_operator_id;
        $this->pulsa->object_type = ($request->object_type) ? $request->object_type : 'pulsa';
        $this->pulsa->pulsa_code = $request->pulsa_code;
        $this->pulsa->pulsa_display_name = $request->pulsa_display_name;
        $this->pulsa->description = $request->description;
        $this->pulsa->value = $request->value;
        $this->pulsa->price = $request->price;
        $this->pulsa->quantity = $request->quantity;
        $this->pulsa->status = ($request->status) ? $request->status : 'inactive';
        $this->pulsa->vendor_price = $request->vendor_price;
        $this->pulsa->displayed = ($request->displayed) ? $request->displayed : 'yes';
        $this->pulsa->is_promo = ($request->promo) ? $request->promo : 'no';
    }

}