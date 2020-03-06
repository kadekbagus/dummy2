<?php

namespace Orbit\Controller\API\v1\Product\Pulsa;

use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\Pulsa\Repository\PulsaRepository as Repository;
use Orbit\Controller\API\v1\Product\Pulsa\Request\TelcoDetailRequest;
use Orbit\Controller\API\v1\Product\Pulsa\Resource\TelcoResource;

/**
 * Get detail of Telco Operator.
 *
 * @author Budi <budi@gotomalls.com>
 */
class TelcoOperatorDetailAPIController extends ControllerAPI
{
    /**
     * Handle Telco Operator list request.
     *
     * @param  PulsaRepository $repo
     * @param  TelcoDetailRequest $request
     *
     * @return Illuminate\Http\Response
     */
    public function getDetail(Repository $repo, TelcoDetailRequest $request)
    {
        $httpCode = 200;

        try {

            $this->response->data = new TelcoResource(
                $repo->telco($request->telco_operator_id)
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
