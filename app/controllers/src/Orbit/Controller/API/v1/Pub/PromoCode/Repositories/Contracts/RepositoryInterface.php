<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts;

/**
 * interface for aby class having capability to check availability of
 * promo code
 *
 * @author Zamroni <zamroni@dominopos.com>
 */
interface RepositoryInterface extends AuthorizerInterface
{
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
    public function checkAvailabilityAndReserveIfAvail();

}
