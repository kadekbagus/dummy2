<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct;

use App;
use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\DigitalProduct\Request\DigitalProductListRequest;
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
     * @return Illuminate\Http\Response
     */
    public function getList()
    {
        $httpCode = 200;

        try {
            // $this->enableQueryLog();

            (new DigitalProductListRequest($this))->validate();

            $this->response->data = App::make(DigitalProductRepository::class)->findProducts();

        } catch (Exception $e) {
            $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
