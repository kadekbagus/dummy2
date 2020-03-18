<?php

namespace Orbit\Controller\API\v1\Rating;

use Config;
use Illuminate\Support\ServiceProvider;
use MongoRating;
use Orbit\Controller\API\v1\Rating\RatingModelInterface;
use Orbit\Helper\MongoDB\Client as MongoClient;

/**
 * Service provider for rating and review feature.
 *
 * @author Budi <budi@gotomalls.com>
 */
class RatingServiceProvider extends ServiceProvider
{
    protected $defer = true;

    public function register()
    {
        // Provide rating model implementation.
        $this->app->singleton(RatingModelInterface::class, function($app) {
            // Can swap rating model implementation here..
            return $app->make(MongoRating::class);
        });

        // Provide mongo client helper.
        $this->app->singleton(MongoClient::class, function($app) {
            return MongoClient::create(Config::get('database.mongodb'));
        });
    }
}
