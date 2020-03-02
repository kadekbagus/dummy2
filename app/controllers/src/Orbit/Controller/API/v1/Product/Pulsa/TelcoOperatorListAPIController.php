<?php

namespace Orbit\Controller\API\v1\Product\Pulsa;

use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\Pulsa\Repository\PulsaRepository as Repository;
use Orbit\Controller\API\v1\Product\Pulsa\Request\TelcoListRequest;
use Orbit\Controller\API\v1\Product\Pulsa\Resource\TelcoCollection;

/**
 * Get list of Telco Operator.
 *
 * @author Budi <budi@gotomalls.com>
 */
class TelcoOperatorListAPIController extends ControllerAPI
{
    /**
     * Handle Telco Operator list request.
     *
     * @param  PulsaRepository $repo
     * @param  TelcoListRequest $request
     *
     * @return Illuminate\Http\Response
     */
    public function getList(Repository $repo, TelcoListRequest $request)
    {
        $httpCode = 200;

        try {

            // Fetch the telco list
            $telco = $repo->getTelcoList();
            $total = clone $telco;
            $total = $total->count();
            $telco = $telco->skip($request->skip ?: 0)
                ->take($request->getTake())->get();

            $this->response->data = new TelcoCollection($telco, $total);

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
