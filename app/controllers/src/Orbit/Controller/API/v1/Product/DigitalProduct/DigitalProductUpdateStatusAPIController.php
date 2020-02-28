<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct;

use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\DigitalProduct\Request\UpdateStatusRequest;
use Orbit\Controller\API\v1\Product\DigitalProduct\Resource\DigitalProductResource;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\DigitalProductRepository as Repository;

/**
 * Handle digital product update status.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class DigitalProductUpdateStatusAPIController extends ControllerAPI
{
    /**
     * Handle Digital Product update request.
     *
     * @return Illuminate\Http\Response
     */
    public function postUpdateStatus(Repository $repo, UpdateStatusRequest $request)
    {
        $httpCode = 200;

        try {

            $this->response->data = new DigitalProductResource(
                $repo->updateStatus($request->id, $request)
            );

        } catch (Exception $e) {
            $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
