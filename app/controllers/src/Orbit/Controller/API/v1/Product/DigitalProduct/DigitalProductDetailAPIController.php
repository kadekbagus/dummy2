<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct;

use App;
use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\DigitalProduct\Request\DigitalProductDetailRequest;
use Orbit\Controller\API\v1\Product\DigitalProduct\Resource\DigitalProductResource;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\DigitalProductRepository;

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
     * @param  DigitalProductRepository $digitalProductRepo
     * @param  DigitalProductListRequest $request
     *
     * @return Illuminate\Http\Response
     */
    public function getDetail(
        DigitalProductRepository $digitalProductRepo,
        DigitalProductDetailRequest $request)
    {
        $httpCode = 200;

        try {

            $this->response->data = new DigitalProductResource(
                $digitalProductRepo->findProduct($request->id)
            );

        } catch (Exception $e) {
            $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
