<?php namespace Orbit\Controller\API\v1\Product\DigitalProduct;

use App;
use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\DigitalProduct\Request\DigitalProductNewRequest;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\DigitalProductRepository;

/**
 * Handle digital product creation.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductNewAPIController extends ControllerAPI
{
    /**
     * Handle Digital Product create request.
     *
     * @return Illuminate\Http\Response
     */
    public function postNew()
    {
        $httpCode = 200;

        try {
            // $this->enableQueryLog();

            with($request = new DigitalProductNewRequest($this))->validate();

            $this->response->data = App::make(DigitalProductRepository::class)->save($request);

        } catch (Exception $e) {
            $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
