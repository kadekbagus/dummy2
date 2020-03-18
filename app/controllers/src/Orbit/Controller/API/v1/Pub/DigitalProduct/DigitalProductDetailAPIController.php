<?php

namespace Orbit\Controller\API\v1\Pub\DigitalProduct;

use Exception;
use Illuminate\Support\Facades\App;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Product\Repository\DigitalProductRepository as Repo;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Request\DigitalProductDetailRequest;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Resource\DigitalProductResource;

/**
 * Get detail of a Digital Product.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductDetailAPIController extends PubControllerAPI
{
    /**
     * Handle Digital Product detail request.
     *
     * @return Illuminate\Http\Response
     */
    public function getDetail(Repo $repo, DigitalProductDetailRequest $request)
    {
        $httpCode = 200;

        try {
            $this->response->data = new DigitalProductResource(
                $repo->findProduct($request->product_id, $request->game_id)
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
