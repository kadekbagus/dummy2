<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct;

use Illuminate\Support\ServiceProvider;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\DigitalProductRepository;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\GameRepository;

/**
 * Service provider for digital product feature.
 *
 * @author Budi <budi@gotomalls.com>
 */
class DigitalProductServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(DigitalProductRepository::class, function($app) {
            return new DigitalProductRepository();
        });

        $this->app->singleton(GameRepository::class, function($app) {
            return new GameRepository();
        });
    }
}
