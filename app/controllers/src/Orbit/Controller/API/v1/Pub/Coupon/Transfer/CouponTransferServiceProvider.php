<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Transfer;

use Illuminate\Support\ServiceProvider;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Repository\CouponTransferRepository;
use Orbit\Controller\API\v1\Pub\Coupon\Transfer\Request\CouponTransferRequest;

/**
 * Service provider for coupon transfer feature.
 */
class CouponTransferServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(CouponTransferRepository::class, function($app) {
            return new CouponTransferRepository(
                $app->make('currentUser'),
                $app->make('issuedCoupon')
            );
        });
    }
}
