<?php

namespace Orbit\Controller\API\v1\BrandProduct\Product;

use Event;
use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\BrandProduct\Repository\BrandProductRepository;
use Orbit\Controller\API\v1\BrandProduct\Product\Request\UpdateRequest;

/**
 * Brand Product Update controller.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ProductUpdateAPIController extends ControllerAPI
{
    /**
     * Update Brand Product handler.
     *
     * @param BrandProductRepository $repo brand product repo
     * @param ValidateRequest $request the request handler
     *
     * @return Illuminate\Http\Response
     */
    public function handle(BrandProductRepository $repo, UpdateRequest $request)
    {
        try {
            $this->beginTransaction();

            $this->response->data = $repo->update($request);

            $this->commit();

            Event::fire(
                'orbit.brandproduct.after.commit',
                [$this->response->data->brand_product_id]
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }

        return $this->render();
    }
}
