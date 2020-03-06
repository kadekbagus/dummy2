<?php

namespace Orbit\Controller\API\v1\Product\Pulsa;

use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\Pulsa\Repository\PulsaListRepository as Repository;
use Orbit\Controller\API\v1\Product\Pulsa\Request\PulsaListRequest;
use Orbit\Controller\API\v1\Product\Pulsa\Resource\PulsaCollection;

/**
 * Get list of pulsa.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class PulsaListAPIController extends ControllerAPI
{
    /**
     * Handle Telco Operator list request.
     *
     * @param  PulsaRepository $repo
     * @param  TelcoListRequest $request
     *
     * @return Illuminate\Http\Response
     */
    public function getList(Repository $repo, PulsaListRequest $request)
    {
        $httpCode = 200;

        try {

            // Fetch the pulsa list
            $pulsa = $repo->getSearchPulsa();
            $total = clone $pulsa;
            $total = $total->count();
            $pulsa = $pulsa->skip($request->skip ?: 0)
                ->take($request->getTake())->get();

            $this->response->data = new PulsaCollection($pulsa, $total);

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
