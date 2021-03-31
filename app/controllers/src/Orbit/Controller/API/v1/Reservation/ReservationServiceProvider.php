<?php

namespace Orbit\Controller\API\v1\Reservation;

use Exception;
use Request;
use Illuminate\Support\ServiceProvider;
use Orbit\Controller\API\v1\BrandProduct\Repository\ReservationRepository;
use Orbit\Controller\API\v1\Reservation\ReservationInterface;
use Orbit\Controller\API\v1\Pub\BrandProduct\Services\BrandProductReservationService;

/**
 * Service provider for brand product feature.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ReservationServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Provide concrete implementation
        $this->app->bind(ReservationInterface::class, function($app, $args)
        {
            $objectType = '';
            if (isset($args[0])) {
                $objectType = $args[0];
            }

            if (Request::has('object_type')) {
                $objectType = Request::input('object_type');
            }

            switch ($objectType) {
                case 'brand_product':
                    return new ReservationRepository();
                    break;

                default:
                    throw new Exception("Unknown reservation type!");
                    break;
            }
        });
    }
}
