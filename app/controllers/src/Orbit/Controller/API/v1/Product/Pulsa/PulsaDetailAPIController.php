<?php

namespace Orbit\Controller\API\v1\Product\Pulsa;

use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\Pulsa\Repository\PulsaDetailRepository as Repository;
use Orbit\Controller\API\v1\Product\Pulsa\Request\PulsaDetailRequest;
use Orbit\Controller\API\v1\Product\Pulsa\Resource\PulsaResource;

/**
 * Get detail of pulsa.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PulsaDetailAPIController extends ControllerAPI
{
    /**
     * Handle pulsa detail request.
     *
     * @param  PulsaDetailRepository $repo
     * @param  PulsaDetailRequest $request
     *
     * @return Illuminate\Http\Response
     */
    public function getDetail(Repository $repo, PulsaDetailRequest $request)
    {
        $httpCode = 200;

        try {

            $this->response->data = new PulsaResource(
                $repo->getDetailPulsa($request->pulsa_item_id)
            );

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
