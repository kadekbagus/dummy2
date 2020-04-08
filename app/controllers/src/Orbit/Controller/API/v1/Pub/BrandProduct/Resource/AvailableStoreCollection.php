<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Resource;

use App;
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
        $request = null;
        if (App::bound('currentRequest')) {
            $request = App::make('currentRequest');
        }

        $maxRecord = 10;
        if (! empty($request)) {
            $maxRecord = (int) $request->take;
        }

        $storeCount = 0;
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

                $storeCount++;

                if ($storeCount === $maxRecord) {
                    break;
                }
            }

            if ($storeCount === $maxRecord) {
                break;
            }
        }

        $this->data['records'] = array_values($this->data['records']);
        $this->data['returned_records'] = count($this->data['records']);

        return $this->data;
    }
}
