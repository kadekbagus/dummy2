<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct;

use App;
use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\DigitalProduct\Request\DigitalProductListRequest;
use Orbit\Controller\API\v1\Product\DigitalProduct\Resource\DigitalProductCollection;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\DigitalProductRepository;

/**
 * Get list of digital product.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductListAPIController extends ControllerAPI
{
    /**
     * Handle Digital Product list request.
     *
     * @param  DigitalProductRepository $digitalProductRepo
     * @param  DigitalProductListRequest $request
     *
     * @return Illuminate\Http\Response
     */
    public function getList(
        DigitalProductRepository $digitalProductRepo,
        DigitalProductListRequest $request)
    {
        $httpCode = 200;

        try {

            // Fetch the digital products
            $digitalProducts = $digitalProductRepo->findProducts();
            $total = clone $digitalProducts;
            $total = $total->count();
            $digitalProducts = $digitalProducts->skip($request->skip)
                ->take($request->take)->get();

            $this->response->data = new DigitalProductCollection(
                $digitalProducts,
                $total
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
