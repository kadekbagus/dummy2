<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationRepositoryInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RuleInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ValidatorInterface;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

class ReservationRepository implements ReservationRepositoryInterface
{
    /**
     * instance class responsible to enforce
     * rule for promo code eligibility
     *
     * @var RuleInterface
     */
    private $promoCodeRule;

    /**
     * instance class responsible to validate input data
     * @var ValidatorInterrface
     */
    private $validator;

    /**
     * instance class responsible to mark promo code reserved
     * @var ReservationInterrface
     */
    private $promoCodeReservation;

    /**
     * instance class responsible to check is user is signed in
     */
    private $authorizer;

    public function __construct(
        ValidatorInterface $validator,
        RuleInterface $promoCodeRule,
        ReservationInterface $promoCodeReservation
    ) {
        $this->validator = $validator;
        $this->promoCodeRule = $promoCodeRule;
        $this->promoCodeReservation = $promoCodeReservation;
    }

    public function authorizer($authorizer)
    {
        $this->authorizer = $authorizer;
        return $this;
    }

    /**
     * unreserved promo code
     *
     * @param string promocode
     * @param string object_id
     * @param string object_type
     * @return StdClass eligibility status
     * @throws ACLForbiddenException
     * @throws InvalidArgsException
     * @throws QueryException
     */
    public function unreserved()
    {
        //check if user is signed in or else throws ACLForbiddenException
        $user = $this->authorizer->getUser();

        //validate input data or else throws InvalidArgsException
        $this->validator->validate();

        $promoData = (object) [
            'promo_code' => OrbitInput::post('promo_code'),
            'object_id' => OrbitInput::post('object_id'),
            'object_type' => OrbitInput::post('object_type'),
            'quantity' => OrbitInput::post('qty'),
        ];

        $this->promoCodeReservation->markReservedNotWaitingPaymentAsAvailable($user, $promoData);
        return (object) [
            'promo_code' => $promoData->promo_code,
            'object_type' => $promoData->object_type,
            'object_id' => $promoData->object_id,
        ];

    }

}
