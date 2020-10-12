<?php

namespace Orbit\Controller\API\v1\Pub\DigitalProduct;

use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\ElectricityRepository;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Request\ElectricityListRequest;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Resource\ElectricityCollection;

/**
 * Get list of electricity nominal.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class ElectricityListAPIController extends PubControllerAPI
{
    /**
     * Handle Electricity list request.
     *
     * @return Illuminate\Http\Response
     */
    public function getList(ElectricityRepository $repo, ElectricityListRequest $request)
    {
        $httpCode = 200;

        try {
            $skip = ($request->skip) ? $request->skip : 0;
            $take = ($request->take) ? $request->take : 50;

            $electric = $repo->getNominal();
            $total = clone $electric;

            $electric = $electric->skip($skip)->take($take)->get();
            $total = $total->count();

            $this->response->data = new ElectricityCollection($electric, $total);

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
