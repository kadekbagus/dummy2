<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts;

/**
 * interface for any class having capability to get promo code detail
 *
 * @author Zamroni <zamroni@dominopos.com>
 */
interface DetailRepositoryInterface extends AuthorizerInterface
{
    /**
     * check availability of promo code and reserved it
     * current logged in user is eligible
     *
     * @param string promocode
     * @return StdClass detail of promo code
     * @throws ACLForbiddenException
     * @throws InvalidArgsException
     * @throws QueryException
     */
    public function getDetail();

}
