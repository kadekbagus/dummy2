<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Resource;

use Orbit\Helper\Resource\Resource;

/**
 * Single Telco Operator resource.
 *
 * @author Budi <budi@gotomalls.com>
 */
class TelcoResource extends Resource
{
    /**
     * Transform resource into response data array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'telco_operator_id' => $this->telco_operator_id,
            'name' => $this->name,
            'country_name' => $this->country_name,
            'country_id' => $this->country_id,
            'identification_prefix_numbers' => $this->identification_prefix_numbers,
            'status' => $this->status,
            'seo_text' => $this->seo_text,
            'media_logo' => $this->transformImagesOld($this->resource),
        ];
    }
}
