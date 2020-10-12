<?php

namespace Orbit\Controller\API\v1\Pub\Product;

use Product;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Product\Request\DetailRequest;
use Orbit\Controller\API\v1\Pub\Product\Repository\ProductAffiliationDetailRepository as Repo;
/**
 * Product Affiliation detail controller.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class ProductAffiliationDetailAPIController extends PubControllerAPI
{
    /**
     * Handle product detail request.
     *
     * @param  Repo             $repo    [description]
     * @param  DetailRequest    $request [description]
     * @return [type]                    [description]
     */
    public function handle(Repo $repo, DetailRequest $request)
    {
        try {

            $this->response->data = $repo->getProduct($request->product_id, $request);

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render();
    }
}
