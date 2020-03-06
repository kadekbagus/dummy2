<?php

namespace Orbit\Controller\API\v1\Product\Pulsa;

use App;
use Exception;
use OrbitShop\API\v1\ControllerAPI;
use Orbit\Controller\API\v1\Product\Pulsa\Request\PulsaCreateRequest;
use Orbit\Controller\API\v1\Product\Pulsa\Resource\PulsaResource;
use Orbit\Controller\API\v1\Product\Pulsa\Repository\PulsaCreateRepository;

/**
 * Handle pulsa creation.
 *
 * @author kadek <kadek@gotomalls.com>
 */
class PulsaNewAPIController extends ControllerAPI
{
    /**
     * Handle pulsa create request.
     *
     * @return Illuminate\Http\Response
     */
    public function postNew(
        PulsaCreateRepository $repo,
        PulsaCreateRequest $request
    ) {
        $httpCode = 200;

        try {

            $this->response->data = new PulsaResource(
                $repo->save($request)
            );

        } catch (Exception $e) {
            $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}