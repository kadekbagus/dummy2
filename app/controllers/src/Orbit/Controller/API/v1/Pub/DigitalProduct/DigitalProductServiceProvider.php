<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct;

use Exception;
use Illuminate\Support\ServiceProvider;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\DigitalProductRepository;
use Orbit\Controller\API\v1\Pub\DigitalProduct\Repository\GameRepository;
use Orbit\Helper\DigitalProduct\Providers\PurchaseProviderBuilder;
use Orbit\Helper\DigitalProduct\Providers\PurchaseProviderInterface;

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

        // Get concrete implementation of the purchase interface.
        // The provider we load would depends on the providerId/name, e.g. ayopay, unipin, etc..
        // This binding requires an instance of purchase available in the container.
        $this->app->singleton(PurchaseProviderInterface::class, function($app) {
            $providerId = null;

            // Resolve provider id/name from the purchase.
            $purchaseDetails = $app->make('purchase')->details;
            if ($purchaseDetails->count() > 0) {
                foreach($purchaseDetails as $detail) {
                    // At the moment we only support digital_product
                    if ($detail->object_type === 'digital_product' && ! empty($detail->provider_product)) {
                        $providerId = $detail->provider_product->provider_name;
                        break;
                    }

                    // else if (in_array($detail->object_type, ['pulsa', 'data_plan'])) {
                    //     $providerId = 'mcash';
                    //     break;
                    // }
                    // else if ($detail->object_type === 'gift_n_coupon') {
                    //     $providerId = 'gift_n';
                    //     break;
                    // }
                }
            }

            if (empty($providerId)) {
                throw new Exception('Can not resolve ProviderId from the purchase!');
            }

            // Get the right provider based on the providerId
            return (new PurchaseProviderBuilder($providerId))->build();
        });
    }
}
