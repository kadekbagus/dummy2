<?php

namespace Orbit\Controller\API\v1\Product\Pulsa;

use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\Pulsa\Repository\PulsaRepository as Repo;
use Orbit\Controller\API\v1\Product\Pulsa\Request\TelcoToggleStatusRequest;
use Orbit\Controller\API\v1\Product\Pulsa\Resource\TelcoResource;

/**
 * Get list of Telco Operator.
 *
 * @author Budi <budi@gotomalls.com>
 */
class TelcoOperatorToggleStatusAPIController extends ControllerAPI
{
    /**
     * Handle Telco Operator update status request.
     *
     * @param  PulsaRepository $repo
     * @param  TelcoToggleStatusRequest $request
     *
     * @return Illuminate\Http\Response
     */
    public function postToggleStatus(Repo $repo, TelcoToggleStatusRequest $request)
    {
        $httpCode = 200;

        try {

            $this->response->data = $repo->telcoToggleStatus($request->id, $request);

        } catch (Exception $e) {
            return $this->handleException($e, true);
        }

        return $this->render($httpCode);
    }
}
