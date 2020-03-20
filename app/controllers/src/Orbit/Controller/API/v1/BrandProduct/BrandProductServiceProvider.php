<?php

namespace Orbit\Controller\API\v1\BrandProduct;

use Illuminate\Support\ServiceProvider;
use Orbit\Helper\Searchable\Elasticsearch\SearchProvider as ESSearchProvider;
use Orbit\Helper\Searchable\SearchProviderInterface;

/**
 * Service provider for brand product feature.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BrandProductServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Provide concrete implementation of Search Provider Interface.
        $this->app->singleton(SearchProviderInterface::class, function($app)
        {
            return $app->make(ESSearchProvider::class);
        });
    }
}
