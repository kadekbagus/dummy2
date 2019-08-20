<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\ServiceProviders;

use Illuminate\Support\ServiceProvider;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RepositoryInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RuleInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ValidatorInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ResponseRendererInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\DetailRepositoryInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationRepositoryInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\PromoCodeRepository;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\PromoCodeRule;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\CouponPromoCodeRule;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\PulsaPromoCodeRule;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\PromoCodeReservation;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\ResponseRenderer;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators\PromoCodeValidator;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators\PromoCodeDetailValidator;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\DetailRepository;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\ReservationRepository;

class PromoCodeServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->bind(RepositoryInterface::class, PromoCodeRepository::class);
        $this->app->bind(RuleInterface::class, function () {
            return new PromoCodeRule(
                new CouponPromoCodeRule(),
                new PulsaPromoCodeRule()
            );
        });
        $this->app->bind(ReservationInterface::class, PromoCodeReservation::class);
        $this->app->bind(ValidatorInterface::class, PromoCodeValidator::class);
        $this->app->bind(ResponseRendererInterface::class, ResponseRenderer::class);

        $this->app->bind(DetailRepositoryInterface::class, function () {
            return new DetailRepository(new PromoCodeDetailValidator());
        });

        $this->app->bind(ReservationRepositoryInterface::class, ReservationRepository::class);
    }

}
