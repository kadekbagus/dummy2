<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct;

use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\DigitalProduct\Request\DetailRequest;
use Orbit\Controller\API\v1\Product\DigitalProduct\Resource\DigitalProductResource;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\DigitalProductRepository as Repository;

/**
 * Handle digital product detail.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductDetailAPIController extends ControllerAPI
{
    /**
     * Handle Digital Product detail request.
     *
     * @param  DigitalProductRepository $repo
     * @param  DetailRequest $request
     *
     * @return Illuminate\Http\Response
     */
    public function getDetail(Repository $repo, DetailRequest $request)
    {
        $httpCode = 200;

        try {

            $this->response->data = new DigitalProductResource(
                $repo->findProduct($request->id)
            );

        } catch (Exception $e) {
            $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
