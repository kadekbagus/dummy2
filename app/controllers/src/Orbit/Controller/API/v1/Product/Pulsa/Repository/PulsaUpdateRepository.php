<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Repository;

use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Pulsa;


class PulsaUpdateRepository {

    private $pulsa = null;

    public function __construct()
    {

    }

    public function update($pulsa_item_id, $request)
    {
        DB::transaction(function() use ($pulsa_item_id, $request)
        {
            $this->pulsa = Pulsa::findOrFail($pulsa_item_id);

            $this->createModelFromRequest($request);

            $this->pulsa->updated_by = null;

            $this->pulsa->save();
        });

        return $this->pulsa;
    }

    private function createModelFromRequest($request)
    {
        ($request->telco_operator_id) ? $this->pulsa->telco_operator_id = $request->telco_operator_id : null;
        ($request->object_type) ? $this->pulsa->object_type = $request->object_type : null;
        ($request->pulsa_code) ? $this->pulsa->pulsa_code = $request->pulsa_code : null;
        ($request->pulsa_display_name) ? $this->pulsa->pulsa_display_name = $request->pulsa_display_name : null;
        ($request->description) ? $this->pulsa->description = $request->description : null;
        ($request->value) ? $this->pulsa->value = $request->value : null;
        ($request->price) ? $this->pulsa->price = $request->price : null;
        $this->pulsa->quantity = $request->quantity;
        ($request->status) ? $this->pulsa->status = $request->status : null;
        ($request->vendor_price) ? $this->pulsa->vendor_price = $request->vendor_price : null;
        ($request->displayed) ? $this->pulsa->displayed = $request->displayed : null;
        ($request->is_promo) ? $this->pulsa->is_promo = $request->is_promo : null;
    }

}