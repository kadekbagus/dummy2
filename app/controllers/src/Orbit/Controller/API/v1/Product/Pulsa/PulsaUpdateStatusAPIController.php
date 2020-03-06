<?php

namespace Orbit\Controller\API\v1\Product\Pulsa;

use App;
use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\Pulsa\Request\PulsaUpdateStatusRequest;
use Orbit\Controller\API\v1\Product\Pulsa\Resource\PulsaResource;
use Orbit\Controller\API\v1\Product\Pulsa\Repository\PulsaUpdateStatusRepository;

/**
 * Handle pulsa update.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class PulsaUpdateStatusAPIController extends ControllerAPI
{
    /**
     * Handle pulsa create request.
     *
     * @return Illuminate\Http\Response
     */
    public function postUpdateStatus(
        PulsaUpdateStatusRepository $repo,
        PulsaUpdateStatusRequest $request
    ) {
        $httpCode = 200;

        try {

            $this->response->data = new PulsaResource(
                $repo->updateStatus($request->pulsa_item_id, $request)
            );

        } catch (Exception $e) {
            $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}