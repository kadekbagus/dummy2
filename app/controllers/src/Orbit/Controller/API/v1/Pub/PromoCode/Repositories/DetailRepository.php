<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\DetailRepositoryInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ValidatorInterface;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Discount;

class DetailRepository implements DetailRepositoryInterface
{

    /**
     * instance class responsible to validate input data
     * @var ValidatorInterrface
     */
    private $validator;

    /**
     * instance class responsible to check is user is signed in
     */
    private $authorizer;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public function authorizer($authorizer)
    {
        $this->authorizer = $authorizer;
        return $this;
    }

    /**
     * GET - get promo code detail
     *
     * @param string promocode
     * @return StdClass discount
     * @throws ACLForbiddenException
     * @throws InvalidArgsException
     * @throws QueryException
     */
    public function getDetail()
    {
        //check if user is signed in or else throws ACLForbiddenException
        $user = $this->authorizer->getUser();

        //validate input data or else throws InvalidArgsException
        $this->validator->user($user)->validate();

        return Discount::where('discount_code', OrbitInput::get('promo_code'))->first();
    }

}
