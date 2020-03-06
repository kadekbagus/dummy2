<?php

namespace Orbit\Controller\API\v1\Product\Pulsa;

use App;
use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\Pulsa\Request\PulsaUpdateRequest;
use Orbit\Controller\API\v1\Product\Pulsa\Resource\PulsaResource;
use Orbit\Controller\API\v1\Product\Pulsa\Repository\PulsaUpdateRepository;

/**
 * Handle pulsa update.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class PulsaUpdateAPIController extends ControllerAPI
{
    /**
     * Handle pulsa create request.
     *
     * @return Illuminate\Http\Response
     */
    public function postUpdate(
        PulsaUpdateRepository $repo,
        PulsaUpdateRequest $request
    ) {
        $httpCode = 200;

        try {

            $this->response->data = new PulsaResource(
                $repo->update($request->pulsa_item_id, $request)
            );

        } catch (Exception $e) {
            $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}