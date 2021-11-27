<?php

namespace Orbit\Controller\API\v1\Pub\Bill;

use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use DigitalProduct;

/**
 * Bill list handler.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BillListAPIController extends PubControllerAPI
{
    public function handle()
    {
        try {
            $this->response->data = DigitalProduct::select(
                    'digital_product_id',
                    'product_name',
                    'product_type',
                    'status'
                )
                ->latest()
                ->active()
                ->whenHas('type', function($query, $type) {
                    if ($type !== 'all') {
                        return $query->where('product_type', $type);
                    }
                    return $query;
                })
                ->whenHas('keyword', function($query, $keyword) {
                    return $query->where('product_name', 'like', "%{$keyword}%");
                })
                ->get();

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
