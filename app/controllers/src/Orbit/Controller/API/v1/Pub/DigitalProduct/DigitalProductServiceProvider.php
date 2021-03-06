<?php

namespace Orbit\Controller\API\v1\Pub\DigitalProduct;

use DigitalProduct;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Orbit\Controller\API\v1\Product\Repository\DigitalProductRepository;
use Orbit\Controller\API\v1\Product\Repository\GameRepository;
use Orbit\Helper\DigitalProduct\Providers\PurchaseProviderBuilder;
use Orbit\Helper\DigitalProduct\Providers\PurchaseProviderInterface;
use Orbit\Helper\MCash\API\BPJSBill\BPJSBill;
use Orbit\Helper\MCash\API\Bill;
use Orbit\Helper\MCash\API\BillInterface;
use Orbit\Helper\MCash\API\ElectricityBill\ElectricityBill;
use Orbit\Helper\MCash\API\InternetProviderBill\InternetProviderBill;
use Orbit\Helper\MCash\API\PBBTaxBill\PBBTaxBill;
use Orbit\Helper\MCash\API\WaterBill\WaterBill;

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
        $this->app->bind(PurchaseProviderInterface::class, function($app, $args = []) {
            $providerId = isset($args['providerId']) ? $args['providerId'] : null;

            // Resolve provider id/name from the purchase.
            if (empty($providerId)) {
                $purchaseDetails = $app->make('purchase')->details;
                if ($purchaseDetails->count() > 0) {
                    foreach($purchaseDetails as $detail) {
                        // At the moment we only support digital_product
                        if (empty($detail->provider_product)) {
                            $detail->load('provider_product');
                        }

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
            }

            // Get the right provider based on the providerId
            return (new PurchaseProviderBuilder($providerId))->build();
        });

        /**
         * Get the actual Bill service for given bill type.
         * @var [type]
         */
        $this->app->singleton(BillInterface::class, function($app, $args = []) {
            $billType = $this->resolveBillType($args);

            switch ($billType) {
                case Bill::ELECTRICITY_BILL:
                    return new ElectricityBill($args);
                    break;

                case Bill::PDAM_BILL:
                    return new WaterBill($args);
                    break;

                case Bill::PBB_TAX_BILL:
                    return new PBBTaxBill($args);
                    break;

                case Bill::BPJS_BILL:
                    return new BPJSBill($args);
                    break;

                case Bill::ISP_BILL:
                    return new InternetProviderBill($args);
                    break;

                default:
                    throw new Exception('cannot resolve bill type!');
                    break;
            }
        });
    }

    /**
     * Resolve bill type automatically from args, request input,
     * or other source.
     *
     * @param  array  $args [description]
     * @return [type]       [description]
     */
    private function resolveBillType($args = [])
    {
        if (isset($args['billType'])) {
            return $args['billType'];
        }

        if (Request::has('bill_type')) {
            return Request::input('bill_type');
        }

        if (App::bound('digitalProduct')) {
            return App::make('digitalProduct')->product_type;
        }

        if (App::bound('providerProduct')) {
            return App::make('providerProduct')->product_type;
        }

        if (App::bound('purchase')) {
            $digitalProductId = App::make('purchase')->details->filter(
                    function($detail) {
                        return $detail->object_type === 'digital_product';
                    }
                )
                ->first()->object_id;

            $billType = DigitalProduct::findOrFail($digitalProductId)
                ->product_type;
        }

        return null;
    }
}
