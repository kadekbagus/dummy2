<?php

namespace Orbit\Controller\API\v1\BrandProduct\Product;

use Event;
use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\BrandProduct\Repository\BrandProductUpdateStatusRepository;
use Orbit\Controller\API\v1\BrandProduct\Product\Request\UpdateStatusRequest;

/**
 * Brand Product Update Status controller.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class ProductUpdateStatusAPIController extends ControllerAPI
{
    /**
     * Update Brand Product handler.
     */
    public function handle(BrandProductUpdateStatusRepository $repo, UpdateStatusRequest $request)
    {
        try {
            $this->beginTransaction();

            $this->response->data = $repo->updateStatus($request);

            $this->commit();

            Event::fire('orbit.brandproduct.after.commit', [
                $this->response->data['brand_product_id']
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }
}
