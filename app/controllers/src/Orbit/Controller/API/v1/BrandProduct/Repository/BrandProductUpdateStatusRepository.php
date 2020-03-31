<?php

namespace Orbit\Controller\API\v1\BrandProduct\Repository;

use App;
use BrandProduct;
use DB;
use Event;
use Exception;
use Request;

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
        $BrandProduct = BrandProduct::findOrFail($request->brand_product_id);

        if ($BrandProduct->status == 'active') {
            $BrandProduct->status = 'inactive';
        } else {
            $BrandProduct->status = 'active';
        }

        $BrandProduct->save();
        return $BrandProduct;
    }
}