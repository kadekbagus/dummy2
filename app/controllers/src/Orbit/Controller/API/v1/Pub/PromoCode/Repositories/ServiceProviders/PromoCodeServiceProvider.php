<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\ServiceProviders;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RepositoryInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RuleInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ValidatorInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\PromoCodeRepository;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\PromoCodeRule;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\PromoCodeReservation;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Validators\PromoCodeValidator;

class PromoCodeServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->bind(RepositoryInterface::class, PromoCodeRepository::class);
        $this->app->bind(RuleInterface::class, PromoCodeRule::class);
        $this->app->bind(ReservationInterface::class, PromoCodeReservation::class);
        $this->app->bind(ValidatorInterface::class, PromoCodeValidator::class);
    }

}
