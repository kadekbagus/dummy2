<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Resource;

use Config;
use Orbit\Helper\Resource\ResourceCollection;

/**
 * Available Store collection class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class AvailableStoreCollection extends ResourceCollection
{
    public function toArray()
    {
        foreach($this->collection as $item) {

            $stores = [];
            if (isset($item['inner_hits'])) {
                $stores = $item['inner_hits']['link_to_stores']['hits']['hits'];
            }

            foreach($stores as $store) {
                $store = $store['_source'];

                if (
                    array_key_exists($store['store_id'], $this->data['records'])
                ) {
                    continue;
                }

                $this->data['records'][$store['store_id']] = [
                    'store_id' => $store['store_id'],
                    'store_name' => $store['store_name'],
                    'mall_id' => $store['mall_id'],
                    'mall_name' => $store['mall_name'],
                ];
            }
        }

        $this->data['records'] = array_values($this->data['records']);

        return $this->data;
    }
}
