<?php

namespace Orbit\Controller\API\v1\BrandProduct\Repository;

use Product;
use BrandProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use OrbitShop\API\v1\OrbitShopAPI;

/**
 * Brand Product Update Status Repository.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class BrandProductUpdateStatusRepository
{
    /**
     * Update Status Brand Product
     *
     * @param  [type] $brandProduct [description]
     * @param  [type] $videos       [description]
     * @return [type]               [description]
     */
    public function updateStatus($request)
    {
        if ($request->has('online_product_status')) {
            return $this->updateOnlineProductStatus($request);
        }

        return $this->updateBrandProductStatus($request);
    }

    public function updateBrandProductStatus($request)
    {
        $brandProduct = DB::transaction(function() use ($request) {
            $BrandProduct = BrandProduct::findOrFail($request->brand_product_id);

            if ($BrandProduct->status == 'active') {
                $BrandProduct->status = 'inactive';
            } else {
                $BrandProduct->status = 'active';
            }

            $BrandProduct->save();

            return $BrandProduct;
        });

        Event::fire('orbit.brandproduct.after.commit', [
            $brandProduct->brand_product_id
        ]);

        return $brandProduct;
    }

    public function updateOnlineProductStatus($request)
    {
        $product = DB::transaction(function() use ($request) {
            $onlineProduct = Product::where(
                    'brand_product_id',
                    $request->brand_product_id
                )->first();

            if (empty($onlineProduct)) {
                OrbitShopAPI::throwInvalidArgument("Online Product not found.");
            }

            if ($onlineProduct->status === 'active') {
                $onlineProduct->status = 'inactive';
            }
            else {
                $onlineProduct->status = 'active';
            }

            $onlineProduct->save();

            return $onlineProduct;
        });

        Event::fire(
            'orbit.newproduct.postupdateproduct.after.commit',
            [$this, $product]
        );

        return $product;
    }
}
