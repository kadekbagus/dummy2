<?php

namespace Orbit\Controller\API\v1\Product\Pulsa\Resource;

use Orbit\Helper\Resource\ResourceCollection;

/**
 * Resource Collection of Telco Operators.
 *
 * @author Budi <budi@gotomalls.com>
 */
class TelcoCollection extends ResourceCollection
{
    /**
     * Transform collection to array as response data.
     *
     * @return array
     */
    public function toArray()
    {
        foreach($this->collection as $item) {
            $this->data['records'][] = [
                'telco_operator_id' => $item->telco_operator_id,
                'name' => $item->name,
                'country_name' => $item->country_name,
                'status' => $item->status,
                'media_logo' => $this->transformImagesOld($item),
            ];
        }

        return $this->data;
    }
}
