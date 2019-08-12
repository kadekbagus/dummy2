<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RepositoryInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RuleInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ReservationInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ValidatorInterface;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

class PromoCodeRepository implements RepositoryInterface
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
     * instance class responsible to validate input data
     * @var ReservationInterrface
     */
    private $promoCodeReservation;

    /**
     * instance class responsible to check is user is signed in
     */
    private $authorizer;

    /**
     * response instance
     */
    private $response;

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
     * check availability of promo code and reserved it
     * current logged in user is eligible
     *
     * @param string promocode
     * @param string object_id
     * @param string object_type
     * @return StdClass eligibility status
     * @throws ACLForbiddenException
     * @throws InvalidArgsException
     * @throws QueryException
     */
    public function checkAvailabilityAndReserveIfAvail()
    {
        //check if user is signed in or else throws ACLForbiddenException
        $user = $this->authorizer->getUser();

        //validate input data or else throws InvalidArgsException
        $this->validator->validate();

        $promoData = (object) [
            'promo_code' => OrbitInput::get('promo_code'),
            'object_id' => OrbitInput::get('object_id'),
            'object_type' => OrbitInput::get('object_type'),
            'quantity' => OrbitInput::get('qty'),
        ];

        $eligbleStatus = $this->promoCodeRule->getEligibleStatus($user, $promoData);
        if ($eligbleStatus->eligible) {
            $this->promoCodeReservation->markAsReserved($user, $promoData);
        }
        return $eligbleStatus;
    }

}
