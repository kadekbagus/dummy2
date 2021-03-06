<?php

namespace Orbit\Controller\API\v1\BrandProduct\Store;

use BppUser;
use DB;
use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\BrandProduct\Repository\BrandProductRepository;
use Orbit\Controller\API\v1\BrandProduct\Store\Resource\StoreCollection;
use Orbit\Controller\API\v1\BrandProduct\Store\Request\ListRequest;
use Tenant;

/**
 * Store List Controller specific for brand product setup.
 *
 * @todo move store listing query into StoreRepository (unified).
 *
 * @author Budi <budi@gotomalls.com>
 */
class StoreListAPIController extends ControllerAPI
{
    // protected $logQuery = true;

    public function handle(ListRequest $request)
    {
        try {
            $sortByMapping = [
                'created_at' => 'merchants.created_at',
                'name' => DB::raw('mall.name'),
            ];

            $user = $request->user();
            $sortBy = $sortByMapping[$request->sortby ?: 'name'];
            $sortMode = $request->sortmode ?: 'asc';

            $prefix = DB::getTablePrefix();

            $records = Tenant::select(
                    'merchants.merchant_id',
                    'merchants.name as store_name',
                    DB::raw('mall.name as mall_name'),
                    DB::raw("CONCAT({$prefix}merchants.name, ' at ', mall.name) as store_location"),
                    'merchants.floor', 'merchants.unit'
                )
                ->join('merchants as mall', 'merchants.parent_id', '=',
                    DB::raw('mall.merchant_id')
                );

            if ($user->user_type === 'brand') {
                $records->join('base_stores', 'merchants.merchant_id', '=',
                        'base_stores.base_store_id'
                    )
                    ->where('merchants.status', 'active')
                    ->where('base_stores.base_merchant_id',
                        $user->base_merchant_id
                    )
                    ->orderBy($sortBy, $sortMode);
            }
            else if ($user->user_type === 'store') {
                $stores = BppUser::with(['stores' => function($query) {
                    $query->select('bpp_user_merchants.merchant_id');
                }])
                ->findOrFail($user->bpp_user_id)
                ->stores->map(function($store) {
                    return $store->merchant_id;
                })->toArray();

                $records->whereIn('merchants.merchant_id', $stores)
                    ->where('merchants.status', 'active');
            }

            $request->has('keyword', function($keyword) use ($records) {
                $records->where(DB::raw('mall.name'), 'like', "%{$keyword}%");
            });

            $total = clone $records;
            $total = $total->count();

            $records = $records->skip($request->skip)
                ->take($request->take)
                ->get();

            $this->response->data = new StoreCollection(
                compact('records', 'total')
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
