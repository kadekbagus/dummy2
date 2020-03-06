<?php

namespace Orbit\Controller\API\v1\Product\DigitalProduct;

use App;
use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\DigitalProduct\Request\UpdateRequest;
use Orbit\Controller\API\v1\Product\DigitalProduct\Resource\DigitalProductResource;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\DigitalProductRepository;

/**
 * Handle digital product update.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductUpdateAPIController extends ControllerAPI
{
    /**
     * Handle Digital Product update request.
     *
     * @return Illuminate\Http\Response
     */
    public function postUpdate(
        DigitalProductRepository $repo,
        UpdateRequest $request
    ) {
        $httpCode = 200;

        try {

            $this->response->data = new DigitalProductResource(
                $repo->update($request->id, $request)
            );

        } catch (Exception $e) {
            $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
