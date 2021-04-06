<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Resource;

use App;
use Config;
use DB;
use Orbit\Helper\Resource\ResourceCollection;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Tenant;

/**
 * Available Store collection class.
 *
 * @todo  inject ValidateRequest to toArray()
 * @todo  Proper count total records.
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
        $currentStoreId = null;
        if (! empty($request)) {
            $maxRecord = (int) $request->take;
            $currentStoreId = $request->store_id;
        }

        $country = OrbitInput::get('country');
        $cities = OrbitInput::get('cities');
        $cities = (array) $cities;

        $storeCount = 0;
        foreach($this->collection as $item) {

            $stores = [];
            if (isset($item['inner_hits'])) {
                $stores = $item['inner_hits']['link_to_stores']['hits']['hits'];
            }

            foreach($stores as $store) {
                $store = $store['_source'];
                $storeId = $store['store_id'];

                if (array_key_exists($storeId, $this->data['records'])) {
                    continue;
                }

                // filter by city
                if (count($cities) !== 0) {

                    if (in_array($store['city'], $cities)) {

                        // filter by country
                         if (isset($country)) {
                             if ($store['country'] === $country) {
                                $this->data['records'][$storeId] = [
                                    'store_id' => $storeId,
                                    'store_name' => $store['store_name'],
                                    'mall_id' => $store['mall_id'],
                                    'mall_name' => $store['mall_name'],
                                    'city' => $store['city'],
                                    'country' => $store['country'],
                                ];
                             }

                         } else {
                             $this->data['records'][$storeId] = [
                                 'store_id' => $storeId,
                                 'store_name' => $store['store_name'],
                                 'mall_id' => $store['mall_id'],
                                 'mall_name' => $store['mall_name'],
                                 'city' => $store['city'],
                                 'country' => $store['country'],
                             ];
                         }

                    }

                } else {
                    
                        // filter by country
                        if (isset($country)) {
                            if ($store['country'] === $country) {
                               $this->data['records'][$storeId] = [
                                   'store_id' => $storeId,
                                   'store_name' => $store['store_name'],
                                   'mall_id' => $store['mall_id'],
                                   'mall_name' => $store['mall_name'],
                                   'city' => $store['city'],
                                   'country' => $store['country'],
                               ];
                            }

                        } else {
                            $this->data['records'][$storeId] = [
                                'store_id' => $storeId,
                                'store_name' => $store['store_name'],
                                'mall_id' => $store['mall_id'],
                                'mall_name' => $store['mall_name'],
                                'city' => $store['city'],
                                'country' => $store['country'],
                            ];
                        }

                }

                $storeCount = count($this->data['records']);

                if ($storeCount === $maxRecord) {
                    break;
                }
            }

            if ($storeCount === $maxRecord) {
                break;
            }
        }
        

        if (! empty($currentStoreId)) {
            $this->data['store'] = $this->getStore($currentStoreId);
        }

        $this->data['records'] = array_values($this->data['records']);
        $this->data['returned_records'] = count($this->data['records']);

        return $this->data;
    }

    /**
     * Get store detail.
     *
     * @param  string $storeId [description]
     * @return [type]          [description]
     */
    private function getStore($storeId = '')
    {
        return Tenant::select(
                'merchants.merchant_id as store_id',
                'merchants.name as store_name',
                DB::raw('mall.merchant_id as mall_id'),
                DB::raw('mall.name as mall_name')
            )
            ->join('merchants as mall', 'merchants.parent_id', '=',
                DB::raw('mall.merchant_id')
            )
            ->where(DB::raw('mall.status'), 'active')
            ->where('merchants.status', 'active')
            ->findOrFail($storeId);
    }
}
