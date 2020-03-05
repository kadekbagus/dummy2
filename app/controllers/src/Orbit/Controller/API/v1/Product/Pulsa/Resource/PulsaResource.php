<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Resource;

use Orbit\Helper\Resource\Resource;
use TelcoOperator;

/**
 * Single pulsa resource.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class PulsaResource extends Resource
{
    /**
     * Transform resource into response data array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'pulsa_item_id' => $this->pulsa_item_id,
            'telco_operator_id' => $this->telco_operator_id,
            'object_type' => $this->object_type,
            'is_promo' => $this->is_promo,
            'pulsa_code' => $this->pulsa_code,
            'pulsa_display_name' => $this->pulsa_display_name,
            'description' => $this->description,
            'value' => $this->value,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'displayed' => $this->displayed,
            'vendor_price' => $this->vendor_price,
            'sold' => isset($this->sold) ? $this->sold : 0,
            'telco_operator' => isset($this->TelcoOperator) ? $this->TelcoOperator : $this->getTelco($this->telco_operator_id),
        ];
    }

    public function getTelco($telco_operator_id)
    {
        $telco = TelcoOperator::where('telco_operator_id', '=', $telco_operator_id)->first();
        return $telco;
    }
}
