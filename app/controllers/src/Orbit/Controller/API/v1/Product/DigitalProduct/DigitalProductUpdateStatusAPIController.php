<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct;

use App;
use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\DigitalProduct\Request\DigitalProductUpdateStatusRequest;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\DigitalProductRepository;

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
    public function postUpdateStatus()
    {
        $httpCode = 200;

        try {
            with($request = new DigitalProductUpdateStatusRequest($this))->validate();

            $this->response->data = App::make(DigitalProductRepository::class)->updateStatus(
                $request->id,
                $request
            );

        } catch (Exception $e) {
            $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
