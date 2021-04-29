<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Repository;

use DB;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Pulsa;


class PulsaUpdateStatusRepository {

    private $pulsa = null;

    public function __construct()
    {

    }

    public function updateStatus($pulsa_item_id)
    {
        DB::transaction(function() use ($pulsa_item_id)
        {
            $this->pulsa = Pulsa::findOrFail($pulsa_item_id);

            if ($this->pulsa->status == 'active') {
                $this->pulsa->status = 'inactive';
            } else {
                $this->pulsa->status = 'active';
            }

            $this->pulsa->updated_by = null;
            $this->pulsa->save();

        });

        return $this->pulsa;
    }

}