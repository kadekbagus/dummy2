<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Detail;

use Illuminate\Support\ServiceProvider;
use Orbit\Controller\API\v1\Pub\Coupon\Detail\Repository\IssuedCouponRepository;
use Orbit\Controller\API\v1\Pub\Coupon\Detail\Repository\PaymentRepository;
use Orbit\Controller\API\v1\Pub\Coupon\Detail\Repository\TimezoneRepository;
use Orbit\Controller\API\v1\Pub\Coupon\Detail\Repository\TenantRepository;

/**
 * Service provider for coupon detail.
 * @author Zamroni <zamroni@dominopos.com>
 */
class CouponDetailServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(IssuedCouponRepository::class, function($app) {
            return new IssuedCouponRepository();
        });

        $this->app->singleton(PaymentRepository::class, function($app) {
            return new PaymentRepository();
        });

        $this->app->singleton(TimezoneRepository::class, function($app) {
            return new TimezoneRepository();
        });

        $this->app->singleton(TenantRepository::class, function($app) {
            return new TenantRepository();
        });
    }
}
